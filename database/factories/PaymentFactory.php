<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\TransactionStatus;
use App\Models\Business;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'payable_type' => Sale::class,
            'payable_id' => Sale::factory(),
            'user_id' => null,
            'method' => fake()->randomElement(PaymentMethod::cases())->value,
            'amount' => fake()->randomFloat(2, 1, 1000),
            'reference' => strtoupper(fake()->bothify('REF########')),
            'status' => TransactionStatus::COMPLETED->value,
            'paid_at' => now(),
            'notes' => null,
        ];
    }
}
