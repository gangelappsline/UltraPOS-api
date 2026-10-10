<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isBusinessOwner();
    }

    public function view(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return true;
        }

        if (! $user->isBusinessOwner()) {
            return false;
        }

        // Same tenant?
        $businessIds = $user->businesses()->pluck('businesses.id');

        return $model->businesses()->whereIn('businesses.id', $businessIds)->exists();
    }

    public function create(User $user): bool
    {
        return $user->isBusinessOwner();
    }

    public function update(User $user, User $model): bool
    {
        return $this->view($user, $model) && $user->id !== $model->id;
    }

    public function delete(User $user, User $model): bool
    {
        return $this->update($user, $model) && ! $model->isAdmin();
    }

    public function assignRole(User $user, User $model): bool
    {
        // Only owners may assign roles, and never the global admin role.
        return $user->isBusinessOwner() && $user->id !== $model->id;
    }

    public function toggleStatus(User $user, User $model): bool
    {
        return $this->delete($user, $model);
    }
}
