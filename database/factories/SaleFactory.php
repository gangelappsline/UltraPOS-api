<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Enums\SaleStatus;
use App\Enums\SaleType;
use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Sale>
 */
class SaleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 10, 2000);
        $tax = round($subtotal * 0.16, 2);
        $total = round($subtotal + $tax, 2);

        return [
            'business_id' => Business::factory(),
            'customer_id' => null,
            'user_id' => null,
            'reference_number' => 'SAL-'.fake()->unique()->numerify('##########'),
            'status' => SaleStatus::COMPLETED->value,
            'payment_status' => PaymentStatus::PAID->value,
            'subtotal' => $subtotal,
            'tax' => $tax,
            'discount' => 0,
            'total' => $total,
            'paid_amount' => $total,
            'change_amount' => 0,
            'notes' => null,
            'sale_type' => SaleType::POS->value,
            'completed_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => [
            'status' => SaleStatus::PENDING->value,
            'payment_status' => PaymentStatus::UNPAID->value,
            'paid_amount' => 0,
            'completed_at' => null,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => ['status' => SaleStatus::CANCELLED->value]);
    }
}
