<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'name' => fake()->randomElement(['Small', 'Medium', 'Large', 'Red', 'Blue']),
            'sku' => 'VAR-'.fake()->unique()->numerify('######'),
            'barcode' => fake()->unique()->ean13(),
            'attributes' => ['size' => fake()->randomElement(['S', 'M', 'L'])],
            'cost_price' => fake()->randomFloat(2, 1, 100),
            'sale_price' => fake()->randomFloat(2, 5, 200),
            'stock' => fake()->randomFloat(2, 0, 100),
            'is_active' => true,
        ];
    }
}
