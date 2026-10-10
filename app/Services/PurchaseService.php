<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PurchaseStatus;
use App\Exceptions\BusinessLogicException;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\User;
use App\Support\Money;
use App\Support\ReferenceGenerator;
use Illuminate\Support\Facades\DB;

/**
 * Supplier purchase orders. Totals computed server-side; receiving a
 * purchase updates product stock + ledger through InventoryService.
 */
class PurchaseService
{
    public function __construct(private readonly InventoryService $inventory)
    {
    }

    /**
     * @param  array<string, mixed>  $data  Validated StorePurchaseRequest payload.
     */
    public function create(User $user, int $businessId, array $data): Purchase
    {
        return DB::transaction(function () use ($user, $businessId, $data): Purchase {
            /** @var Purchase $purchase */
            $purchase = Purchase::create([
                'business_id' => $businessId,
                'supplier_id' => $data['supplier_id'] ?? null,
                'user_id' => $user->id,
                'reference_number' => ReferenceGenerator::generate(
                    Purchase::class,
                    'reference_number',
                    (string) config('ultrapos.references.purchase', 'PUR'),
                    $businessId,
                ),
                'status' => $data['status'] ?? PurchaseStatus::DRAFT->value,
                'discount' => Money::round($data['discount'] ?? 0),
                'notes' => $data['notes'] ?? null,
                'purchased_at' => $data['purchased_at'] ?? now()->toDateString(),
            ]);

            $this->syncItems($purchase, $data['items'], $businessId);

            return $purchase->refresh()->load(['items.product', 'supplier', 'user']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Purchase $purchase, array $data): Purchase
    {
        if (! $purchase->isEditable()) {
            throw new BusinessLogicException(
                sprintf('A %s purchase cannot be modified.', $purchase->status->value)
            );
        }

        return DB::transaction(function () use ($purchase, $data): Purchase {
            $purchase->update(collect($data)->except('items')->all());

            if (isset($data['items'])) {
                $purchase->items()->delete();
                $this->syncItems($purchase, $data['items'], $purchase->business_id);
            }

            return $purchase->refresh()->load(['items.product', 'supplier', 'user']);
        });
    }

    /** Receive the purchase: stock IN for every line + ledger entries. */
    public function receive(Purchase $purchase, User $user): Purchase
    {
        if ($purchase->status === PurchaseStatus::RECEIVED) {
            throw new BusinessLogicException('This purchase has already been received.');
        }

        if ($purchase->status === PurchaseStatus::CANCELLED) {
            throw new BusinessLogicException('A cancelled purchase cannot be received.');
        }

        return DB::transaction(function () use ($purchase, $user): Purchase {
            /** @var Purchase $locked */
            $locked = Purchase::query()->whereKey($purchase->getKey())->lockForUpdate()->firstOrFail();
            $locked->load('items.product');

            if ($locked->status === PurchaseStatus::RECEIVED) {
                throw new BusinessLogicException('This purchase has already been received.');
            }

            foreach ($locked->items as $item) {
                $this->inventory->receivePurchase(
                    $item->product,
                    (float) $item->quantity,
                    $item->variant,
                    $locked,
                    $user,
                );
            }

            $locked->forceFill([
                'status' => PurchaseStatus::RECEIVED->value,
                'purchased_at' => $locked->purchased_at ?? now()->toDateString(),
            ])->save();

            \App\Events\PurchaseReceived::dispatch($locked, $user);

            return $locked->refresh()->load(['items.product', 'supplier', 'user']);
        });
    }

    /** Cancel a purchase (only draft/ordered). */
    public function cancel(Purchase $purchase, User $user, string $reason): Purchase
    {
        if (! $purchase->isEditable()) {
            throw new BusinessLogicException(
                sprintf('A %s purchase cannot be cancelled.', $purchase->status->value)
            );
        }

        $purchase->forceFill([
            'status' => PurchaseStatus::CANCELLED->value,
            'notes' => trim(($purchase->notes ?? '').' | Cancelled: '.$reason, ' |'),
        ])->save();

        return $purchase->refresh()->load(['items.product', 'supplier', 'user']);
    }

    /**
     * Build line items and recompute purchase totals server-side.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncItems(Purchase $purchase, array $items, int $businessId): void
    {
        $subtotal = 0.0;
        $taxTotal = 0.0;

        $products = Product::query()
            ->where('business_id', $businessId)
            ->whereKey(collect($items)->pluck('product_id')->unique()->all())
            ->get()
            ->keyBy('id');

        foreach ($items as $item) {
            $product = $products->get((int) $item['product_id']);

            if ($product === null) {
                throw new BusinessLogicException("Product [{$item['product_id']}] does not belong to your business.");
            }

            $quantity = (float) $item['quantity'];
            $unitCost = isset($item['unit_cost']) && $item['unit_cost'] !== null
                ? Money::round((float) $item['unit_cost'])
                : Money::round((float) $product->cost_price);

            $lineSubtotal = Money::multiply($quantity, $unitCost);
            $lineTax = Money::tax($lineSubtotal, (float) $product->tax_rate);

            PurchaseItem::create([
                'purchase_id' => $purchase->id,
                'product_id' => $product->id,
                'product_variant_id' => $item['product_variant_id'] ?? null,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'subtotal' => $lineSubtotal,
                'tax' => $lineTax,
                'total' => Money::add($lineSubtotal, $lineTax),
            ]);

            $subtotal += $lineSubtotal;
            $taxTotal += $lineTax;
        }

        $subtotal = Money::round($subtotal);
        $taxTotal = Money::round($taxTotal);
        $discount = Money::min((float) $purchase->discount, $subtotal);

        $purchase->forceFill([
            'subtotal' => $subtotal,
            'tax' => $taxTotal,
            'discount' => $discount,
            'total' => Money::round($subtotal - $discount + $taxTotal),
        ])->save();
    }
}
