<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Enums\SaleType;
use App\Enums\TransactionStatus;
use App\Events\SaleCancelled;
use App\Events\SaleCompleted;
use App\Events\SaleRefunded;
use App\Exceptions\BusinessLogicException;
use App\Mail\SaleReceiptMail;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Support\Money;
use App\Support\ReferenceGenerator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * POS sale orchestration.
 *
 * Architectural decisions:
 *  - Totals are ALWAYS computed server-side from locked product rows; the
 *    client only sends product_id / variant_id / quantity / line discount.
 *  - The whole creation runs inside DB::transaction with lockForUpdate on
 *    products, variants and the customer, preventing oversell races between
 *    concurrent POS terminals.
 *  - Side effects that may fail independently (receipt email) run through
 *    queued listeners/events AFTER commit.
 */
class SaleService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly PaymentService $payments,
    ) {
    }

    /**
     * Create and complete a POS sale.
     *
     * @param  array<string, mixed>  $data  Validated StoreSaleRequest payload.
     */
    public function create(User $seller, array $data): Sale
    {
        $business = $seller->activeBusiness();

        if ($business === null) {
            throw new BusinessLogicException('Your account is not associated with an active business.');
        }

        if (! $business->isOperational()) {
            throw new BusinessLogicException('This business is inactive or its subscription is not usable.');
        }

        return DB::transaction(function () use ($business, $seller, $data): Sale {
            /* 1 ── Lock every row we are about to touch ─────────────── */
            $items = collect($data['items']);

            $products = Product::query()
                ->where('business_id', $business->id)
                ->whereKey($items->pluck('product_id')->unique()->all())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $variantIds = $items->pluck('product_variant_id')->filter()->unique()->all();

            /** @var Collection<int, ProductVariant> $variants */
            $variants = $variantIds !== []
                ? ProductVariant::query()->whereKey($variantIds)->lockForUpdate()->get()->keyBy('id')
                : new Collection();

            $customer = null;
            if (! empty($data['customer_id'])) {
                $customer = Customer::query()
                    ->where('business_id', $business->id)
                    ->whereKey((int) $data['customer_id'])
                    ->lockForUpdate()
                    ->first();

                if ($customer === null) {
                    throw new BusinessLogicException('The selected customer does not belong to your business.');
                }
            }

            /* 2 ── Compute totals server-side ────────────────────────── */
            $lines = [];
            $subtotal = 0.0;
            $taxTotal = 0.0;
            $lineDiscountTotal = 0.0;

            foreach ($items as $item) {
                /** @var Product|null $product */
                $product = $products->get((int) $item['product_id']);

                if ($product === null) {
                    throw new BusinessLogicException(
                        "Product [{$item['product_id']}] does not exist in your business."
                    );
                }

                if (! $product->is_active) {
                    throw new BusinessLogicException("Product \"{$product->name}\" is inactive and cannot be sold.");
                }

                $variant = null;
                if (! empty($item['product_variant_id'])) {
                    $variant = $variants->get((int) $item['product_variant_id']);

                    if ($variant === null || $variant->product_id !== $product->id) {
                        throw new BusinessLogicException(
                            "Variant [{$item['product_variant_id']}] does not belong to product \"{$product->name}\"."
                        );
                    }

                    if (! $variant->is_active) {
                        throw new BusinessLogicException("Variant \"{$variant->name}\" is inactive.");
                    }
                }

                $quantity = (float) $item['quantity'];
                $unitPrice = Money::round(
                    $variant !== null && (float) $variant->sale_price > 0
                        ? (float) $variant->sale_price
                        : (float) $product->sale_price
                );

                $lineDiscount = Money::min((float) ($item['discount'] ?? 0), $quantity * $unitPrice);
                $lineGross = Money::subtract($quantity * $unitPrice, $lineDiscount);
                $lineTax = Money::tax($lineGross, (float) $product->tax_rate);

                // Stock availability (rows are already locked; the definitive
                // decrement happens in step 4 through InventoryService).
                if ($product->track_stock && ! (bool) config('ultrapos.allow_negative_stock', false)) {
                    $available = (float) ($variant?->stock ?? $product->stock);

                    if ($quantity > $available) {
                        throw new BusinessLogicException(
                            sprintf('Insufficient stock for "%s": available %s, requested %s.',
                                $variant?->name ?? $product->name,
                                number_format($available, 2),
                                number_format($quantity, 2),
                            ),
                            [
                                'product_id' => $product->id,
                                'product_name' => $product->name,
                                'variant_id' => $variant?->id,
                                'available' => $available,
                                'requested' => $quantity,
                            ]
                        );
                    }
                }

                $lines[] = [
                    'product' => $product,
                    'variant' => $variant,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount' => $lineDiscount,
                    'tax' => $lineTax,
                    'subtotal' => $lineGross,
                    'total' => Money::add($lineGross, $lineTax),
                ];

                $subtotal += $lineGross;
                $taxTotal += $lineTax;
                $lineDiscountTotal += $lineDiscount;
            }

            $subtotal = Money::round($subtotal);
            $taxTotal = Money::round($taxTotal);

            $globalDiscount = Money::min(Money::round($data['discount'] ?? 0), $subtotal);
            $total = Money::round($subtotal - $globalDiscount + $taxTotal);

            /* 3 ── Persist the sale + line items ─────────────────────── */
            $payLater = (bool) ($data['pay_later'] ?? false);

            if ($payLater && $customer === null) {
                throw new BusinessLogicException('Credit sales (pay_later) require a customer.');
            }

            /** @var Sale $sale */
            $sale = Sale::create([
                'business_id' => $business->id,
                'customer_id' => $customer?->id,
                'user_id' => $seller->id,
                'reference_number' => ReferenceGenerator::generate(
                    Sale::class,
                    'reference_number',
                    (string) config('ultrapos.references.sale', 'SAL'),
                    (int) $business->id,
                ),
                'status' => SaleStatus::COMPLETED->value,
                'payment_status' => \App\Enums\PaymentStatus::UNPAID->value,
                'subtotal' => $subtotal,
                'tax' => $taxTotal,
                'discount' => Money::add($lineDiscountTotal, $globalDiscount),
                'total' => $total,
                'paid_amount' => 0,
                'change_amount' => 0,
                'notes' => $data['notes'] ?? null,
                'sale_type' => $data['sale_type'] ?? SaleType::POS->value,
                'completed_at' => now(),
            ]);

            /* 4 ── Line items + stock consumption (ledger entries) ───── */
            foreach ($lines as $line) {
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $line['product']->id,
                    'product_variant_id' => $line['variant']?->id,
                    'product_name' => $line['variant']?->name
                        ? $line['product']->name.' — '.$line['variant']->name
                        : $line['product']->name,
                    'unit_price' => $line['unit_price'],
                    'quantity' => $line['quantity'],
                    'discount' => $line['discount'],
                    'tax' => $line['tax'],
                    'subtotal' => $line['subtotal'],
                    'total' => $line['total'],
                ]);

                $this->inventory->consumeForSale(
                    $line['product'],
                    $line['quantity'],
                    $line['variant'],
                    $sale,
                    $seller,
                );
            }

            /* 5 ── Payment / credit ──────────────────────────────────── */
            if ($payLater) {
                // Whole balance goes to the customer's credit account.
                $this->payments->chargeCustomerBalance($customer, $sale->balance());
            } elseif (! empty($data['payment'])) {
                $this->payments->applyToSale($sale, $seller, $data['payment']);
            }

            $sale->refresh();

            /* 6 ── Loyalty points (only on actually-paid totals) ─────── */
            if ($customer !== null) {
                $pointsRate = (int) config('ultrapos.loyalty_points_per_unit', 1);
                $points = (int) floor((float) $sale->paid_amount * $pointsRate);

                if ($points > 0) {
                    $customer->increment('loyalty_points', $points);
                }
            }

            /* 7 ── Post-commit side effects via events ────────────────── */
            SaleCompleted::dispatch(
                $sale,
                $seller,
                isset($data['receipt_email']) ? (string) $data['receipt_email'] : null,
                (bool) ($data['send_receipt'] ?? false),
            );

            return $sale->load(['items.product', 'items.variant', 'payments', 'customer', 'business', 'user']);
        });
    }

    /** Cancel a sale: restock items, refund cash, roll loyalty back. */
    public function cancel(Sale $sale, User $user, string $reason, bool $restock = true): Sale
    {
        if (! $sale->canBeCancelled()) {
            throw new BusinessLogicException(
                sprintf('A %s sale cannot be cancelled.', $sale->status->value)
            );
        }

        return DB::transaction(function () use ($sale, $user, $reason, $restock): Sale {
            /** @var Sale $locked */
            $locked = Sale::query()->whereKey($sale->getKey())->lockForUpdate()->firstOrFail();
            $locked->load(['items.product', 'items.variant', 'payments', 'customer']);

            if (! $locked->canBeCancelled()) {
                throw new BusinessLogicException(
                    sprintf('A %s sale cannot be cancelled.', $locked->status->value)
                );
            }

            if ($restock) {
                foreach ($locked->items as $item) {
                    if ($item->product !== null) {
                        $this->inventory->returnFromSale(
                            $item->product,
                            (float) $item->quantity,
                            $item->variant,
                            $locked,
                            $user,
                            'Sale cancelled: '.$reason,
                        );
                    }
                }
            }

            // Mark completed payments as refunded and move cash out of the drawer.
            foreach ($locked->payments as $payment) {
                if ($payment->status !== TransactionStatus::COMPLETED) {
                    continue;
                }

                $payment->forceFill(['status' => TransactionStatus::REFUNDED->value])->save();

                if ($payment->method->affectsCashRegister()) {
                    app(CashRegisterService::class)->recordRefundCash($user, $locked, (float) $payment->amount);
                }
            }

            // Credit sale: release the customer's charged balance.
            if ($locked->customer !== null && (float) $locked->paid_amount < (float) $locked->total) {
                $owed = Money::subtract($locked->total, $locked->paid_amount);
                $newBalance = max(0, Money::subtract($locked->customer->current_balance, $owed));
                $locked->customer->forceFill(['current_balance' => $newBalance])->save();
            }

            // Roll loyalty points back.
            if ($locked->customer !== null) {
                $pointsRate = (int) config('ultrapos.loyalty_points_per_unit', 1);
                $points = (int) floor((float) $locked->paid_amount * $pointsRate);

                if ($points > 0) {
                    $locked->customer->decrement('loyalty_points', min($points, (int) $locked->customer->loyalty_points));
                }
            }

            $locked->forceFill([
                'status' => SaleStatus::CANCELLED->value,
                'notes' => trim(($locked->notes ?? '').' | Cancelled: '.$reason, ' |'),
            ])->save();

            SaleCancelled::dispatch($locked, $user, $reason);

            return $locked->refresh()->load(['items.product', 'payments', 'customer', 'business', 'user']);
        });
    }

    /**
     * Full or partial refund of a COMPLETED sale.
     *
     * @param  array<string, mixed>  $data  Validated RefundSaleRequest payload.
     */
    public function refund(Sale $sale, User $user, array $data): Sale
    {
        if ($sale->status !== SaleStatus::COMPLETED) {
            throw new BusinessLogicException('Only completed sales can be refunded.');
        }

        $restock = (bool) ($data['restock'] ?? true);

        return DB::transaction(function () use ($sale, $user, $data, $restock): Sale {
            /** @var Sale $locked */
            $locked = Sale::query()->whereKey($sale->getKey())->lockForUpdate()->firstOrFail();
            $locked->load(['items', 'payments', 'customer']);

            if ($locked->status !== SaleStatus::COMPLETED) {
                throw new BusinessLogicException('Only completed sales can be refunded.');
            }

            // Determine refunded items (all when not specified).
            $refundedLines = collect($locked->items);
            if (! empty($data['items'])) {
                $requested = collect($data['items'])->keyBy('sale_item_id');
                $refundedLines = $locked->items->filter(fn (SaleItem $item) => $requested->has($item->id));

                if ($refundedLines->isEmpty()) {
                    throw new BusinessLogicException('None of the provided items belong to this sale.');
                }
            }

            // Refund amount: explicit > items sum > everything already paid.
            $refundAmount = Money::round(
                $data['amount']
                    ?? ($refundedLines->count() < $locked->items->count()
                        ? $refundedLines->sum(fn (SaleItem $item): float => (float) $item->total)
                        : (float) $locked->paid_amount)
            );

            if ($refundAmount <= 0) {
                throw new BusinessLogicException('There is nothing to refund on this sale.');
            }

            if ($refundAmount > (float) $locked->paid_amount) {
                throw new BusinessLogicException(
                    'The refund amount cannot exceed the paid amount of the sale.',
                    ['paid_amount' => (float) $locked->paid_amount, 'requested' => $refundAmount]
                );
            }

            // Refund payment record (method: original or explicitly requested).
            $originalPayment = $locked->payments->firstWhere('status', TransactionStatus::COMPLETED->value);
            $method = isset($data['refund_method'])
                ? PaymentMethod::from($data['refund_method'])
                : ($originalPayment?->method ?? PaymentMethod::CASH);

            Payment::create([
                'business_id' => $locked->business_id,
                'payable_type' => Sale::class,
                'payable_id' => $locked->id,
                'user_id' => $user->id,
                'method' => $method->value,
                'amount' => $refundAmount,
                'reference' => $originalPayment?->reference,
                'status' => TransactionStatus::REFUNDED->value,
                'paid_at' => now(),
                'notes' => 'Refund: '.$data['reason'],
            ]);

            if ($method->affectsCashRegister()) {
                app(CashRegisterService::class)->recordRefundCash($user, $locked, $refundAmount);
            }

            // Restock refunded lines.
            if ($restock) {
                foreach ($refundedLines as $item) {
                    $quantity = (float) $item->quantity;

                    if (! empty($data['items'])) {
                        $requestedQty = collect($data['items'])->firstWhere('sale_item_id', $item->id)['quantity'] ?? null;
                        $quantity = $requestedQty !== null ? min((float) $requestedQty, (float) $item->quantity) : $quantity;
                    }

                    if ($item->product !== null && $quantity > 0) {
                        $this->inventory->returnFromSale(
                            $item->product,
                            $quantity,
                            $item->variant,
                            $locked,
                            $user,
                            'Sale refunded: '.$data['reason'],
                        );
                    }
                }
            }

            // Roll loyalty back proportionally.
            if ($locked->customer !== null) {
                $pointsRate = (int) config('ultrapos.loyalty_points_per_unit', 1);
                $points = (int) floor($refundAmount * $pointsRate);

                if ($points > 0) {
                    $locked->customer->decrement('loyalty_points', min($points, (int) $locked->customer->loyalty_points));
                }
            }

            $isFullRefund = $refundedLines->count() === $locked->items->count()
                && Money::equals($refundAmount, (float) $locked->paid_amount);

            $locked->forceFill([
                'paid_amount' => max(0, Money::subtract($locked->paid_amount, $refundAmount)),
                'notes' => trim(($locked->notes ?? '').' | Refund: '.$data['reason'], ' |'),
            ])->save();

            $locked->forceFill([
                'payment_status' => $locked->resolvePaymentStatus()->value,
                'status' => $isFullRefund ? SaleStatus::REFUNDED->value : SaleStatus::COMPLETED->value,
            ])->save();

            SaleRefunded::dispatch($locked, $user, $refundAmount, $data['reason']);

            return $locked->refresh()->load(['items.product', 'payments', 'customer', 'business', 'user']);
        });
    }

    /** Structured receipt payload (endpoint + email share this shape). */
    public function receiptData(Sale $sale): array
    {
        $sale->loadMissing(['items.product', 'items.variant', 'payments', 'customer', 'business', 'user']);

        return [
            'business' => [
                'name' => $sale->business?->name,
                'legal_name' => $sale->business?->legal_name,
                'tax_id' => $sale->business?->tax_id,
                'address' => $sale->business?->address,
                'phone' => $sale->business?->phone,
                'email' => $sale->business?->email,
                'currency' => $sale->business?->currency,
            ],
            'sale' => [
                'reference_number' => $sale->reference_number,
                'status' => $sale->status->value,
                'payment_status' => $sale->payment_status->value,
                'sale_type' => $sale->sale_type->value,
                'completed_at' => $sale->completed_at?->toIso8601String(),
                'subtotal' => (float) $sale->subtotal,
                'tax' => (float) $sale->tax,
                'discount' => (float) $sale->discount,
                'total' => (float) $sale->total,
                'paid_amount' => (float) $sale->paid_amount,
                'change_amount' => (float) $sale->change_amount,
                'balance' => $sale->balance(),
            ],
            'items' => $sale->items->map(fn (SaleItem $item): array => [
                'name' => $item->product_name,
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'discount' => (float) $item->discount,
                'tax' => (float) $item->tax,
                'total' => (float) $item->total,
            ])->all(),
            'payments' => $sale->payments->map(fn (Payment $payment): array => [
                'method' => $payment->method->value,
                'amount' => (float) $payment->amount,
                'status' => $payment->status->value,
                'reference' => $payment->reference,
                'paid_at' => $payment->paid_at?->toIso8601String(),
            ])->all(),
            'customer' => $sale->customer !== null ? [
                'name' => $sale->customer->name,
                'email' => $sale->customer->email,
                'tax_id' => $sale->customer->tax_id,
            ] : null,
            'served_by' => $sale->user?->name,
        ];
    }

    /** Queue the receipt email to a customer address (or the sale customer). */
    public function sendReceipt(Sale $sale, ?string $email = null): string
    {
        $sale->loadMissing('customer');

        $to = $email ?: $sale->customer?->email;

        if ($to === null || $to === '') {
            throw new BusinessLogicException(
                'No email address available. Provide "email" or attach a customer with email to the sale.'
            );
        }

        Mail::to($to)->queue(new SaleReceiptMail($sale, $this->receiptData($sale)));

        return $to;
    }

    /** Aggregated daily summary for the dashboard / reports. */
    public function dailySummary(int $businessId, ?Carbon $date = null): array
    {
        $date ??= Carbon::today();

        $base = fn () => Sale::query()
            ->where('business_id', $businessId)
            ->whereDate('created_at', $date->toDateString());

        $completed = (clone $base)()->completed();

        $totals = $completed->selectRaw(
            'COUNT(*) as sales_count,
             COALESCE(SUM(subtotal), 0) as subtotal,
             COALESCE(SUM(tax), 0) as tax,
             COALESCE(SUM(discount), 0) as discount,
             COALESCE(SUM(total), 0) as total,
             COALESCE(SUM(paid_amount), 0) as paid_amount'
        )->first();

        $byMethod = Payment::query()
            ->where('business_id', $businessId)
            ->whereDate('paid_at', $date->toDateString())
            ->where('status', TransactionStatus::COMPLETED->value)
            ->where('payable_type', Sale::class)
            ->groupBy('method')
            ->selectRaw('method, COUNT(*) as count, COALESCE(SUM(amount), 0) as amount')
            ->get();

        $byType = (clone $base)()
            ->completed()
            ->groupBy('sale_type')
            ->selectRaw('sale_type, COUNT(*) as count, COALESCE(SUM(total), 0) as total')
            ->get();

        $topProducts = SaleItem::query()
            ->whereHas('sale', fn ($q) => $q->where('business_id', $businessId)
                ->whereDate('created_at', $date->toDateString())
                ->completed())
            ->groupBy('product_name')
            ->selectRaw('product_name, SUM(quantity) as units, SUM(total) as revenue')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get();

        return [
            'date' => $date->toDateString(),
            'sales_count' => (int) ($totals->sales_count ?? 0),
            'cancelled_count' => (clone $base)()->status(SaleStatus::CANCELLED)->count(),
            'subtotal' => (float) ($totals->subtotal ?? 0),
            'tax' => (float) ($totals->tax ?? 0),
            'discount' => (float) ($totals->discount ?? 0),
            'total' => (float) ($totals->total ?? 0),
            'paid_amount' => (float) ($totals->paid_amount ?? 0),
            'outstanding' => (float) ((clone $base)()->completed()
                ->whereColumn('paid_amount', '<', 'total')->sum('total')),
            'by_payment_method' => $byMethod,
            'by_sale_type' => $byType,
            'top_products' => $topProducts,
        ];
    }
}
