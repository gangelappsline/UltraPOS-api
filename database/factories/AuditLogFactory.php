<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AuditAction;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AuditLog>
 */
class AuditLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => null,
            'user_id' => null,
            'action' => fake()->randomElement(AuditAction::cases())->value,
            'model_type' => Product::class,
            'model_id' => fake()->numberBetween(1, 1000),
            'old_values' => ['sale_price' => '10.00'],
            'new_values' => ['sale_price' => '12.50'],
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }
}
