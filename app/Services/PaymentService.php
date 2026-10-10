<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\TransactionStatus;
use App\Exceptions\BusinessLogicException;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Payment application against payable documents (sales, customer balances,
 * purchases). Keeps paid_amount / payment_status / cash drawer in sync.
 */
class PaymentService
{
    public function __construct(private readonly CashRegisterService $cashRegisters)
    {
    }

    /**
     * Apply a payment to a sale. Handles overpayment (change) for cash.
     *
     * @param  array<string, mixed>  $paymentData  {method, amount, reference?, notes?}
     */
    public function applyToSale(Sale $sale, User $user, array $paymentData): Payment
    {
        $method = PaymentMethod::from($paymentData['method']);
        $tendered = Money::round($paymentData['amount']);
        $balance = $sale->balance();

        if ($balance <= 0) {
            throw new BusinessLogicException('This sale is already fully paid.');
        }

        if ($method === PaymentMethod::CREDIT && $sale->customer_id === null) {
            throw new BusinessLogicException('Credit payments require a customer on the sale.');
        }

        return DB::transaction(function () use ($sale, $user, $method, $tendered, $paymentData): Payment {
            /** @var Sale $locked */
            $locked = Sale::query()->whereKey($sale->getKey())->lockForUpdate()->firstOrFail();
            $balance = $locked->balance();

            if ($balance <= 0) {
                throw new BusinessLogicException('This sale is already fully paid.');
            }

            // Cash may be over-tendered: the excess is change, not payment.
            $applied = $method === PaymentMethod::CASH
                ? Money::min($tendered, $balance)
                : min($tendered, $balance);
            $applied = Money::round($applied);

            if ($method === PaymentMethod::CASH && $tendered > $balance) {
                $locked->forceFill(['change_amount' => Money::round($tendered - $balance)])->save();
            }

            $payment = Payment::create([
                'business_id' => $locked->business_id,
                'payable_type' => Sale::class,
                'payable_id' => $locked->id,
                'user_id' => $user->id,
                'method' => $method->value,
                'amount' => $applied,
                'reference' => $paymentData['reference'] ?? null,
                'status' => TransactionStatus::COMPLETED->value,
                'paid_at' => isset($paymentData['paid_at']) ? $paymentData['paid_at'] : now(),
                'notes' => $paymentData['notes'] ?? null,
            ]);

            $locked->forceFill(['paid_amount' => Money::add($locked->paid_amount, $applied)])->save();
            $locked->forceFill(['payment_status' => $locked->resolvePaymentStatus()->value])->save();

            // Credit sales push the unpaid balance to the customer account.
            if ($method === PaymentMethod::CREDIT && $locked->customer !== null) {
                $this->chargeCustomerBalance($locked->customer, $applied);
            }

            if ($method->affectsCashRegister()) {
                $this->cashRegisters->recordSaleCash($user, $locked, $applied);
            }

            $sale->refresh();

            return $payment;
        });
    }

    /**
     * Generic endpoint POST /payments (sale | customer | purchase).
     *
     * @param  array<string, mixed>  $data
     */
    public function createForDocument(User $user, string $payableClass, int $payableId, array $data): Payment
    {
        /** @var Sale|Customer|Purchase $payable */
        $payable = $payableClass::query()->whereKey($payableId)->first();

        if ($payable === null) {
            throw new BusinessLogicException('The selected document does not exist in your business.');
        }

        $method = PaymentMethod::from($data['method']);
        $amount = Money::round($data['amount']);

        return match (true) {
            $payable instanceof Sale => $this->applyToSale($payable, $user, $data),

            $payable instanceof Customer => DB::transaction(function () use ($payable, $user, $method, $amount, $data): Payment {
                /** @var Customer $locked */
                $locked = Customer::query()->whereKey($payable->getKey())->lockForUpdate()->firstOrFail();

                if ($amount > (float) $locked->current_balance) {
                    throw new BusinessLogicException(
                        'The payment exceeds the customer outstanding balance.',
                        ['current_balance' => (float) $locked->current_balance, 'amount' => $amount]
                    );
                }

                $payment = Payment::create([
                    'business_id' => $locked->business_id,
                    'payable_type' => Customer::class,
                    'payable_id' => $locked->id,
                    'user_id' => $user->id,
                    'method' => $method->value,
                    'amount' => $amount,
                    'reference' => $data['reference'] ?? null,
                    'status' => TransactionStatus::COMPLETED->value,
                    'paid_at' => $data['paid_at'] ?? now(),
                    'notes' => $data['notes'] ?? null,
                ]);

                $locked->forceFill([
                    'current_balance' => Money::subtract($locked->current_balance, $amount),
                ])->save();

                if ($method->affectsCashRegister()) {
                    $this->cashRegisters->recordSaleCash($user, $locked, $amount);
                }

                return $payment;
            }),

            $payable instanceof Purchase => DB::transaction(function () use ($payable, $user, $method, $amount, $data): Payment {
                /** @var Purchase $locked */
                $locked = Purchase::query()->whereKey($payable->getKey())->lockForUpdate()->firstOrFail();

                if ($amount > $locked->balance()) {
                    throw new BusinessLogicException(
                        'The payment exceeds the purchase outstanding balance.',
                        ['balance' => $locked->balance(), 'amount' => $amount]
                    );
                }

                $payment = Payment::create([
                    'business_id' => $locked->business_id,
                    'payable_type' => Purchase::class,
                    'payable_id' => $locked->id,
                    'user_id' => $user->id,
                    'method' => $method->value,
                    'amount' => $amount,
                    'reference' => $data['reference'] ?? null,
                    'status' => TransactionStatus::COMPLETED->value,
                    'paid_at' => $data['paid_at'] ?? now(),
                    'notes' => $data['notes'] ?? null,
                ]);

                $locked->forceFill(['paid_amount' => Money::add($locked->paid_amount, $amount)])->save();

                return $payment;
            }),

            default => throw new BusinessLogicException('Unsupported payable type.'),
        };
    }

    /** Refund (totally or partially) a completed payment. */
    public function refund(Payment $payment, User $user, ?float $amount = null, ?string $reason = null): Payment
    {
        if ($payment->status !== TransactionStatus::COMPLETED) {
            throw new BusinessLogicException('Only completed payments can be refunded.');
        }

        $refundAmount = Money::round($amount ?? (float) $payment->amount);

        if ($refundAmount > (float) $payment->amount) {
            throw new BusinessLogicException('Refund amount cannot exceed the original payment.');
        }

        return DB::transaction(function () use ($payment, $user, $refundAmount, $reason): Payment {
            /** @var Payment $locked */
            $locked = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

            $refund = Payment::create([
                'business_id' => $locked->business_id,
                'payable_type' => $locked->payable_type,
                'payable_id' => $locked->payable_id,
                'user_id' => $user->id,
                'method' => $locked->method->value,
                'amount' => $refundAmount,
                'reference' => $locked->reference,
                'status' => TransactionStatus::REFUNDED->value,
                'paid_at' => now(),
                'notes' => $reason !== null
                    ? 'Refund of payment #'.$locked->id.': '.$reason
                    : 'Refund of payment #'.$locked->id,
            ]);

            if (Money::equals($refundAmount, (float) $locked->amount)) {
                $locked->forceFill(['status' => TransactionStatus::REFUNDED->value])->save();
            }

            // Roll the document balances back.
            $payable = $locked->payable;

            if ($payable instanceof Sale) {
                $payable->forceFill([
                    'paid_amount' => max(0, Money::subtract($payable->paid_amount, $refundAmount)),
                ])->save();
                $payable->forceFill(['payment_status' => $payable->resolvePaymentStatus()->value])->save();

                if ($locked->method->affectsCashRegister()) {
                    $this->cashRegisters->recordRefundCash($user, $payable, $refundAmount);
                }
            } elseif ($payable instanceof Customer) {
                $payable->forceFill([
                    'current_balance' => Money::add($payable->current_balance, $refundAmount),
                ])->save();
            } elseif ($payable instanceof Purchase) {
                $payable->forceFill([
                    'paid_amount' => max(0, Money::subtract($payable->paid_amount, $refundAmount)),
                ])->save();
            }

            return $refund;
        });
    }

    /** Push an amount to a customer's credit balance (credit limit aware). */
    public function chargeCustomerBalance(Customer $customer, float $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        if ($customer->hasCreditEnabled()
            && (float) $customer->current_balance + $amount > (float) $customer->credit_limit) {
            throw new BusinessLogicException(
                'This operation would exceed the customer credit limit.',
                [
                    'credit_limit' => (float) $customer->credit_limit,
                    'current_balance' => (float) $customer->current_balance,
                    'attempted_charge' => $amount,
                ]
            );
        }

        $customer->forceFill([
            'current_balance' => Money::add($customer->current_balance, $amount),
        ])->save();
    }
}
