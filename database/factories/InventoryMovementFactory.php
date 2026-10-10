<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\InventoryMovementType;
use App\Models\Business;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InventoryMovement>
 */
class InventoryMovementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'product_id' => Product::factory(),
            'product_variant_id' => null,
            'user_id' => null,
            'type' => fake()->randomElement(InventoryMovementType::cases())->value,
            'quantity' => fake()->randomFloat(2, -20, 50),
            'previous_stock' => 100,
            'new_stock' => 110,
            'reference_type' => null,
            'reference_id' => null,
            'notes' => fake()->sentence(),
        ];
    }
}
