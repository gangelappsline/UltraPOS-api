<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UnitOfMeasure>
 */
class UnitOfMeasureFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'name' => fake()->randomElement(['Piece', 'Kilogram', 'Litre', 'Metre', 'Box']),
            'abbreviation' => fake()->unique()->randomElement(['pc', 'kg', 'lt', 'm', 'bx']),
            'type' => fake()->randomElement(['unit', 'weight', 'volume', 'length']),
            'is_active' => true,
        ];
    }
}
