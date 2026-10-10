<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->words(3, true);
        $cost = fake()->randomFloat(2, 1, 200);

        return [
            'business_id' => Business::factory(),
            'category_id' => null,
            'brand_id' => null,
            'unit_id' => null,
            'sku' => 'SKU-'.fake()->unique()->numerify('######'),
            'barcode' => fake()->unique()->ean13(),
            'name' => ucwords($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 9999999),
            'description' => fake()->paragraph(),
            'cost_price' => $cost,
            'sale_price' => round($cost * fake()->randomFloat(2, 1.2, 2.5), 2),
            'tax_rate' => fake()->randomElement([0, 8, 16]),
            'stock' => fake()->randomFloat(2, 0, 500),
            'min_stock' => 10,
            'max_stock' => 1000,
            'track_stock' => true,
            'is_active' => true,
            'is_featured' => fake()->boolean(20),
            'expires_at' => null,
            'metadata' => null,
        ];
    }

    public function lowStock(): static
    {
        return $this->state(fn (): array => ['stock' => 2, 'min_stock' => 10]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn (): array => ['stock' => 0]);
    }

    public function expiringSoon(): static
    {
        return $this->state(fn (): array => ['expires_at' => now()->addDays(10)->toDateString()]);
    }

    public function untracked(): static
    {
        return $this->state(fn (): array => ['track_stock' => false, 'stock' => 0]);
    }
}
