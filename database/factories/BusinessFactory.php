<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BusinessType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Business>
 */
class BusinessFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(BusinessType::cases());

        return [
            'owner_id' => User::factory(),
            'name' => fake()->company(),
            'legal_name' => fake()->company().' LLC',
            'tax_id' => strtoupper(fake()->bothify('TAX##########')),
            'business_type' => $type->value,
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->e164PhoneNumber(),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->state(),
            'country' => 'MX',
            'postal_code' => fake()->postcode(),
            'currency' => 'MXN',
            'timezone' => 'America/Mexico_City',
            'is_active' => true,
            'settings' => ['locale' => 'en', 'receipt_footer' => 'Thank you for your purchase!'],
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    public function ofType(BusinessType $type): static
    {
        return $this->state(fn (): array => ['business_type' => $type->value]);
    }
}
