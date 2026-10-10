<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Purchase;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PurchaseItem>
 */
class PurchaseItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cost = fake()->randomFloat(2, 1, 100);
        $qty = fake()->randomFloat(2, 1, 100);

        return [
            'purchase_id' => Purchase::factory(),
            'product_id' => Product::factory(),
            'product_variant_id' => null,
            'quantity' => $qty,
            'unit_cost' => $cost,
            'subtotal' => round($cost * $qty, 2),
            'tax' => round($cost * $qty * 0.16, 2),
            'total' => round($cost * $qty * 1.16, 2),
        ];
    }
}
