<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'user_id' => null,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->e164PhoneNumber(),
            'address' => fake()->streetAddress(),
            'tax_id' => strtoupper(fake()->bothify('CUS########')),
            'credit_limit' => fake()->randomElement([0, 1000, 5000, 10000]),
            'current_balance' => 0,
            'loyalty_points' => fake()->numberBetween(0, 500),
            'notes' => null,
            'is_active' => true,
        ];
    }

    public function withDebt(float $balance = 350.50): static
    {
        return $this->state(fn (): array => ['current_balance' => $balance, 'credit_limit' => max(5000, $balance * 2)]);
    }
}
