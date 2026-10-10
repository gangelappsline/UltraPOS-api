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
            ],
        );

        if ($admin->trashed()) {
            $admin->restore();
        }

        // email_verified_at is intentionally NOT in User::$fillable (a client
        // must never verify its own e-mail), so it is set outside of mass
        // assignment. Without this the seeded admin is created unverified.
        if (! $admin->hasVerifiedEmail()) {
            $admin->forceFill(['email_verified_at' => now()])->save();
        }

        $admin->syncRoles([UserRole::ADMIN->value]);
    }
}
