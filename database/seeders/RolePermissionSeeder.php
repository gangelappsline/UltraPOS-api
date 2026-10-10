<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates the four system roles and a granular permission catalog.
 * Route-level enforcement uses roles (EnsureUserHasRole); permissions are
 * available for finer-grained Gate checks and future UI-driven ACLs.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'users.view', 'users.manage',
            'businesses.view', 'businesses.manage', 'businesses.settings',
            'catalog.view', 'catalog.manage',
            'inventory.view', 'inventory.manage',
            'sales.view', 'sales.create', 'sales.cancel', 'sales.refund',
            'customers.view', 'customers.manage', 'customers.payments',
            'suppliers.view', 'suppliers.manage',
            'purchases.view', 'purchases.manage', 'purchases.receive',
            'payments.view', 'payments.manage', 'payments.refund',
            'cash.view', 'cash.manage',
            'expenses.view', 'expenses.manage',
            'reports.view', 'dashboard.view',
            'subscriptions.manage',
            'admin.access',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'api');
            Permission::findOrCreate($permission, 'web');
        }

        // Admin: everything.
        $admin = Role::findOrCreate(UserRole::ADMIN->value, 'web');
        $admin->syncPermissions(Permission::where('guard_name', 'web')->pluck('name')->all());

        // Business owner: full tenant control (no admin.access).
        $business = Role::findOrCreate(UserRole::BUSINESS->value, 'web');
        $business->syncPermissions(array_values(array_diff($permissions, ['admin.access'])));

        // Seller: POS operation.
        $seller = Role::findOrCreate(UserRole::SELLER->value, 'web');
        $seller->syncPermissions([
            'catalog.view', 'inventory.view', 'inventory.manage',
            'sales.view', 'sales.create',
            'customers.view', 'customers.manage', 'customers.payments',
            'payments.view', 'payments.manage',
            'cash.view', 'cash.manage',
            'dashboard.view',
        ]);

        // Customer: read-only own data.
        $customer = Role::findOrCreate(UserRole::CUSTOMER->value, 'web');
        $customer->syncPermissions([]);
    }
}
