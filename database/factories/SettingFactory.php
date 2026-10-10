<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Setting>
 */
class SettingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'key' => 'setting.'.fake()->unique()->word(),
            'value' => ['enabled' => true],
            'group' => fake()->randomElement(['general', 'receipt', 'taxes', 'loyalty']),
        ];
    }
}
