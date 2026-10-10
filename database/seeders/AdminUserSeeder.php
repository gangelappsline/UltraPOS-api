<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Creates the default global administrator from .env values.
 * CHANGE THE DEFAULT PASSWORD IMMEDIATELY in any real deployment.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::withTrashed()->firstOrCreate(
            ['email' => env('DEFAULT_ADMIN_EMAIL', 'admin@ultrapos.test')],
            [
                'name' => env('DEFAULT_ADMIN_NAME', 'System Administrator'),
                'password' => env('DEFAULT_ADMIN_PASSWORD', 'ChangeMe123!'),
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        if ($admin->trashed()) {
            $admin->restore();
        }

        $admin->syncRoles([UserRole::ADMIN->value]);
    }
}
