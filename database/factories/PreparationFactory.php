<?php

namespace Database\Factories;

use App\Enums\MeasurementUnit;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Preparation>
 */
class PreparationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'unit' => fake()->randomElement(MeasurementUnit::values()),
            'company_id' => Company::factory(),
        ];
    }
}
