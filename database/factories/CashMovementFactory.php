<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CashMovementType;
use App\Models\CashRegister;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CashMovement>
 */
class CashMovementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cash_register_id' => CashRegister::factory(),
            'user_id' => null,
            'type' => fake()->randomElement(CashMovementType::cases())->value,
            'amount' => fake()->randomFloat(2, 10, 1000),
            'reference_type' => null,
            'reference_id' => null,
            'notes' => fake()->sentence(4),
        ];
    }
}
