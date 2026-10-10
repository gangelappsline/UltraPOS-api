<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'user_id' => null,
            'category' => fake()->randomElement(['rent', 'utilities', 'salaries', 'supplies', 'maintenance']),
            'description' => fake()->sentence(),
            'amount' => fake()->randomFloat(2, 10, 3000),
            'expense_date' => now()->subDays(fake()->numberBetween(0, 60))->toDateString(),
            'receipt' => null,
        ];
    }
}
