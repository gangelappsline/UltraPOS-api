<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Business;
use App\Models\User;

/**
 * Admins bypass every policy via Gate::before (AuthServiceProvider).
 */
class BusinessPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isBusinessOwner();
    }

    public function view(User $user, Business $business): bool
    {
        return $user->belongsToBusiness((int) $business->getKey());
    }

    /** Only admins create extra tenants through /businesses (registration
     * creates the first tenant via /auth/register). */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Business $business): bool
    {
        return $business->owner_id === $user->id
            || $user->businesses()->whereKey($business->getKey())->wherePivot('role', 'business')->exists();
    }

    public function delete(User $user, Business $business): bool
    {
        return $business->owner_id === $user->id;
    }

    public function manageSettings(User $user, Business $business): bool
    {
        return $this->update($user, $business);
    }
}
