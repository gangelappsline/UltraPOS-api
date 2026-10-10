<?php

declare(strict_types=1);

namespace App\Support\Traits;

use App\Enums\UserRole;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Multi-tenancy enforcement for tenant-owned models.
 *
 * 1. Global scope: every query is filtered by the authenticated user's
 *    active business_id (admins bypass the filter; console/queue contexts
 *    without an authenticated user are NOT filtered so jobs/seeders work).
 * 2. Auto-fills business_id on create from the current tenant context.
 *
 * Decision: a Global Scope (instead of manual where() calls) makes tenant
 * isolation the DEFAULT — forgetting a filter becomes impossible.
 */
trait BelongsToBusiness
{
    public static function bootBelongsToBusiness(): void
    {
        static::addGlobalScope('business_scope', function (Builder $builder): void {
            // TenantContext (and NOT auth('api') directly): resolving the
            // Passport guard in console contexts requires the OAuth keys and
            // used to break `php artisan migrate --seed` on fresh installs.
            $user = TenantContext::user();

            if ($user !== null && $user->hasRole(UserRole::ADMIN->value)) {
                return;
            }

            if ($user === null) {
                // No HTTP auth context (console, queue, public routes).
                return;
            }

            $businessId = $user->active_business_id;

            if ($businessId === null) {
                // Authenticated but without tenant context: see nothing.
                $builder->whereRaw('1 = 0');

                return;
            }

            $builder->where($builder->qualifyColumn('business_id'), $businessId);
        });

        static::creating(function (Model $model): void {
            if (! empty($model->getAttribute('business_id'))) {
                return;
            }

            $user = TenantContext::user();

            // Business bound by the business.scope middleware for this request.
            $contextBusiness = app()->bound('current_business')
                ? app('current_business')
                : null;

            $model->setAttribute('business_id', match (true) {
                $user !== null && $user->active_business_id !== null => $user->active_business_id,
                $contextBusiness !== null => $contextBusiness->getKey(),
                default => null,
            });
        });
    }
}
