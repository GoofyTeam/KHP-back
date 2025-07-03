<?php

namespace Database\Factories;

use App\Enums\PreparationTypeEnum;
use App\Enums\UnitEnum;
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
            'unit' => fake()->randomElement(UnitEnum::values()),
            'type' => fake()->randomElement(PreparationTypeEnum::values()),
            'company_id' => Company::factory(),
        ];
    }
}
