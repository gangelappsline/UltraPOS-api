<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Supplier>
 */
class SupplierFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'name' => fake()->company(),
            'contact_name' => fake()->name(),
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->e164PhoneNumber(),
            'address' => fake()->address(),
            'tax_id' => strtoupper(fake()->bothify('SUP########')),
            'notes' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
