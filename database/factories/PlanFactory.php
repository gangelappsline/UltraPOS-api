<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BillingCycle;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Plan>
 */
class PlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->randomElement(['Starter', 'Growth', 'Pro', 'Enterprise', 'Basic']);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'price' => fake()->randomElement([9, 29, 79, 199]),
            'billing_cycle' => fake()->randomElement(BillingCycle::cases())->value,
            'features' => ['Unlimited sales', 'Email support'],
            'limits' => ['users' => 5, 'products' => 500],
            'is_active' => true,
        ];
    }
}
