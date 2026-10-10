<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserRole;
use App\Exceptions\BusinessLogicException;
use App\Models\Business;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Staff management inside a tenant (business owners manage their team).
 */
class UserService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(User $actor, Business $business, array $filters = []): LengthAwarePaginator
    {
        return $business->users()
            ->when($filters['role'] ?? null, fn ($q, string $role) => $q->wherePivot('role', $role))
            ->when($filters['search'] ?? null, function ($q, string $term): void {
                $like = '%'.mb_strtolower($term).'%';
                $q->where(function ($qq) use ($like): void {
                    $qq->whereRaw('LOWER(name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(email) LIKE ?', [$like]);
                });
            })
            ->when(isset($filters['is_active']), fn ($q) => $q->where('is_active', (bool) $filters['is_active']))
            ->orderByDesc('users.id')
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();
    }

    /**
     * Create a staff member and attach it to the tenant with a contextual role.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(Business $business, array $data): User
    {
        return DB::transaction(function () use ($business, $data): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'phone' => $data['phone'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            $role = UserRole::from($data['role']);
            $user->syncRoles([$role->value]);

            $business->users()->attach($user->id, [
                'role' => $role->value,
                'joined_at' => now(),
            ]);

            return $user->load('roles');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $attributes = collect($data)->except(['role', 'password'])->all();

            if (! empty($data['password'])) {
                $attributes['password'] = $data['password']; // 'hashed' cast
            }

            $user->update($attributes);

            if (! empty($data['role'])) {
                $this->assignRole($user, $data['role']);
            }

            return $user->refresh()->load('roles');
        });
    }

    /** Soft-delete + detach from tenants + revoke tokens. */
    public function delete(User $actor, User $user): void
    {
        if ($user->isAdmin()) {
            throw new BusinessLogicException('Global administrators cannot be deleted through this endpoint.');
        }

        if ($actor->id === $user->id) {
            throw new BusinessLogicException('You cannot delete your own account.');
        }

        DB::transaction(function () use ($user): void {
            $user->tokens()->update(['revoked' => true]);
            $user->businesses()->detach();
            $user->delete();
        });
    }

    /** Replace the contextual role everywhere it applies. */
    public function assignRole(User $user, string $role): User
    {
        $roleEnum = UserRole::from($role);

        DB::transaction(function () use ($user, $roleEnum): void {
            $user->syncRoles([$roleEnum->value]);

            foreach ($user->businesses()->get() as $business) {
                $business->users()->updateExistingPivot($user->id, ['role' => $roleEnum->value]);
            }
        });

        return $user->refresh()->load('roles');
    }

    /** Enable/disable a user (disabled users cannot obtain tokens). */
    public function toggleStatus(User $actor, User $user): User
    {
        if ($actor->id === $user->id) {
            throw new BusinessLogicException('You cannot deactivate your own account.');
        }

        if ($user->isAdmin() && $user->is_active) {
            throw new BusinessLogicException('Global administrators cannot be deactivated through this endpoint.');
        }

        $user->update(['is_active' => ! $user->is_active]);

        if (! $user->is_active) {
            $user->tokens()->update(['revoked' => true]);
        }

        return $user->refresh();
    }
}
