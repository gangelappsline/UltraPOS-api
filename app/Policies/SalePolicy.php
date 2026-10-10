<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Sale;
use App\Models\User;

class SalePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isBusinessOwner() || $user->isSeller();
    }

    public function view(User $user, Sale $sale): bool
    {
        return $this->viewAny($user) && $user->belongsToBusiness($sale->business_id);
    }

    public function create(User $user): bool
    {
        return $user->isBusinessOwner() || $user->isSeller();
    }

    /** Cancelling/refunding is restricted to the business owner. */
    public function cancel(User $user, Sale $sale): bool
    {
        return $user->isBusinessOwner() && $user->belongsToBusiness($sale->business_id);
    }

    public function refund(User $user, Sale $sale): bool
    {
        return $this->cancel($user, $sale);
    }

    public function sendReceipt(User $user, Sale $sale): bool
    {
        return $this->view($user, $sale);
    }
}
