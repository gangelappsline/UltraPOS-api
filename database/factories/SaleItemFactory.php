<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SaleItem>
 */
class SaleItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $price = fake()->randomFloat(2, 1, 200);
        $qty = fake()->randomFloat(2, 1, 10);
        $subtotal = round($price * $qty, 2);

        return [
            'sale_id' => Sale::factory(),
            'product_id' => null,
            'product_variant_id' => null,
            'product_name' => fake()->words(3, true),
            'unit_price' => $price,
            'quantity' => $qty,
            'discount' => 0,
            'tax' => round($subtotal * 0.16, 2),
            'subtotal' => $subtotal,
            'total' => round($subtotal * 1.16, 2),
        ];
    }
}
