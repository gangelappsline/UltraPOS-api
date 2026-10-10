<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    /** Seed the application's database. */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            PlanSeeder::class,
            AdminUserSeeder::class,
            DemoBusinessSeeder::class,
        ]);

        // Passport OAuth2 clients (personal access + password grant), so that
        // `migrate --seed` alone leaves a working login on a fresh install.
        // `passport:install` cannot be used here: it publishes Passport's own
        // migrations, which this project already ships, and it prompts in TTYs.
        if ($this->command !== null) {
            $this->command->call('ultrapos:passport-clients');
        } else {
            Artisan::call('ultrapos:passport-clients');
        }
    }
}
