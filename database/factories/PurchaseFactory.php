<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PurchaseStatus;
use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Purchase>
 */
class PurchaseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 100, 10000);

        return [
            'business_id' => Business::factory(),
            'supplier_id' => null,
            'user_id' => null,
            'reference_number' => 'PUR-'.fake()->unique()->numerify('##########'),
            'status' => PurchaseStatus::DRAFT->value,
            'subtotal' => $subtotal,
            'tax' => round($subtotal * 0.16, 2),
            'discount' => 0,
            'total' => round($subtotal * 1.16, 2),
            'paid_amount' => 0,
            'notes' => null,
            'purchased_at' => now()->toDateString(),
        ];
    }
}
