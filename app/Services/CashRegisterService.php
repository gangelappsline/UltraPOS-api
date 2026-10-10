<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CashMovementType;
use App\Enums\CashRegisterStatus;
use App\Exceptions\BusinessLogicException;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Cash drawer sessions. Business rule: a user can only hold ONE open
 * register at a time (checked under a transaction).
 */
class CashRegisterService
{
    /** The open session of a user, if any. */
    public function openRegisterFor(User $user): ?CashRegister
    {
        return CashRegister::query()
            ->where('user_id', $user->id)
            ->open()
            ->first();
    }

    /** Open a new drawer session with its opening cash movement. */
    public function open(User $user, float $openingAmount, ?string $notes = null): CashRegister
    {
        $business = $user->activeBusiness();

        if ($business === null) {
            throw new BusinessLogicException('Your account is not associated with an active business.');
        }

        return DB::transaction(function () use ($user, $business, $openingAmount, $notes): CashRegister {
            // Serialize concurrent "open" requests for the same user.
            $existing = CashRegister::query()
                ->where('user_id', $user->id)
                ->where('status', CashRegisterStatus::OPEN->value)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                throw new BusinessLogicException(
                    'You already have an open cash register. Close it before opening a new one.',
                    ['open_cash_register_id' => $existing->id]
                );
            }

            /** @var CashRegister $register */
            $register = CashRegister::create([
                'business_id' => $business->id,
                'user_id' => $user->id,
                'opened_at' => now(),
                'opening_amount' => $openingAmount,
                'status' => CashRegisterStatus::OPEN->value,
                'notes' => $notes,
            ]);

            $this->recordMovement($register, $user, CashMovementType::OPENING, $openingAmount, 'Register opening');

            return $register->load('user');
        });
    }

    /** Close a session, computing expected amount and difference. */
    public function close(CashRegister $register, User $user, float $closingAmount, ?string $notes = null): CashRegister
    {
        if (! $register->isOpen()) {
            throw new BusinessLogicException('This cash register is already closed.');
        }

        if ($register->user_id !== $user->id && ! $user->isBusinessOwner() && ! $user->isAdmin()) {
            throw new BusinessLogicException('You can only close your own cash register.');
        }

        return DB::transaction(function () use ($register, $user, $closingAmount, $notes): CashRegister {
            /** @var CashRegister $locked */
            $locked = CashRegister::query()->whereKey($register->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->isOpen()) {
                throw new BusinessLogicException('This cash register is already closed.');
            }

            $expected = $locked->movementsBalance();

            $locked->forceFill([
                'status' => CashRegisterStatus::CLOSED->value,
                'closed_at' => now(),
                'closing_amount' => $closingAmount,
                'expected_amount' => $expected,
                'difference' => round($closingAmount - $expected, 2),
                'notes' => $notes ?? $locked->notes,
            ])->save();

            $this->recordMovement($locked, $user, CashMovementType::CLOSING, $closingAmount, 'Register closing');

            return $locked->refresh()->load('user');
        });
    }

    /** Manual cash-in (deposit) / cash-out (withdrawal). */
    public function addMovement(
        CashRegister $register,
        User $user,
        CashMovementType $type,
        float $amount,
        ?string $notes = null,
    ): CashMovement {
        if (! $register->isOpen()) {
            throw new BusinessLogicException('This cash register is closed.');
        }

        if ($type === CashMovementType::WITHDRAWAL) {
            $balance = $register->movementsBalance();
            if ($amount > $balance) {
                throw new BusinessLogicException(
                    'Withdrawal exceeds the current drawer balance.',
                    ['balance' => $balance, 'requested' => $amount]
                );
            }
        }

        return $this->recordMovement($register, $user, $type, $amount, $notes);
    }

    /** Low-level movement writer (assumes validations already happened). */
    public function recordMovement(
        CashRegister $register,
        ?User $user,
        CashMovementType $type,
        float $amount,
        ?string $notes = null,
        ?Model $reference = null,
    ): CashMovement {
        return CashMovement::create([
            'cash_register_id' => $register->id,
            'user_id' => $user?->id,
            'type' => $type->value,
            'amount' => round(abs($amount), 2),
            'reference_type' => $reference !== null ? $reference::class : null,
            'reference_id' => $reference?->getKey(),
            'notes' => $notes,
        ]);
    }

    /** Record sale cash into the seller's open register (if any). */
    public function recordSaleCash(?User $seller, Model $sale, float $amount): void
    {
        if ($seller === null || $amount <= 0) {
            return;
        }

        $register = $this->openRegisterFor($seller);

        if ($register !== null) {
            $this->recordMovement($register, $seller, CashMovementType::SALE, $amount,
                'Cash from sale '.$sale->getAttribute('reference_number'), $sale);
        }
    }

    /** Record a cash refund into the seller's open register (if any). */
    public function recordRefundCash(?User $user, Model $document, float $amount): void
    {
        if ($user === null || $amount <= 0) {
            return;
        }

        $register = $this->openRegisterFor($user);

        if ($register !== null) {
            $this->recordMovement($register, $user, CashMovementType::REFUND, $amount,
                'Refund for '.$document->getAttribute('reference_number'), $document);
        }
    }
}
