<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CashRegisterStatus;
use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CashRegister>
 */
class CashRegisterFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'user_id' => User::factory(),
            'opened_at' => now()->subHours(4),
            'closed_at' => null,
            'opening_amount' => fake()->randomFloat(2, 100, 2000),
            'closing_amount' => null,
            'expected_amount' => null,
            'difference' => null,
            'status' => CashRegisterStatus::OPEN->value,
            'notes' => null,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CashRegisterStatus::CLOSED->value,
            'closed_at' => now(),
            'closing_amount' => $attributes['opening_amount'] + 500,
            'expected_amount' => $attributes['opening_amount'] + 500,
            'difference' => 0,
        ]);
    }
}
