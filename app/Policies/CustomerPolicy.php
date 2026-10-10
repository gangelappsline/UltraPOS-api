<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isBusinessOwner() || $user->isSeller();
    }

    public function view(User $user, Customer $customer): bool
    {
        return $this->viewAny($user) && $user->belongsToBusiness($customer->business_id);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Customer $customer): bool
    {
        return $this->create($user) && $user->belongsToBusiness($customer->business_id);
    }

    /** Credit limit changes and deletions are owner-only. */
    public function delete(User $user, Customer $customer): bool
    {
        return $user->isBusinessOwner() && $user->belongsToBusiness($customer->business_id);
    }

    public function recordPayment(User $user, Customer $customer): bool
    {
        return $this->viewAny($user) && $user->belongsToBusiness($customer->business_id);
    }
}
