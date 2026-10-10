<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'description' => 'For a single counter: one register, up to 3 users.',
                'price' => 19.00,
                'billing_cycle' => 'monthly',
                'features' => ['1 cash register', '3 users', '500 products', 'Sales & inventory', 'Email support'],
                'limits' => ['registers' => 1, 'users' => 3, 'products' => 500],
            ],
            [
                'name' => 'Growth',
                'slug' => 'growth',
                'description' => 'For growing stores: multiple registers and full analytics.',
                'price' => 49.00,
                'billing_cycle' => 'monthly',
                'features' => ['5 cash registers', '15 users', '5,000 products', 'Full reports', 'Credit accounts & loyalty', 'Priority support'],
                'limits' => ['registers' => 5, 'users' => 15, 'products' => 5000],
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'Multi-branch operations with API-first integrations.',
                'price' => 490.00,
                'billing_cycle' => 'yearly',
                'features' => ['Unlimited registers', 'Unlimited users', 'Unlimited products', 'Custom integrations', 'Dedicated support'],
                'limits' => ['registers' => -1, 'users' => -1, 'products' => -1],
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], [...$plan, 'is_active' => true]);
        }
    }
}
