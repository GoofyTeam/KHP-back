<?php

namespace Database\Factories;

use App\Enums\MeasurementUnit;
use App\Models\Category;
use App\Models\Company;
use App\Models\Preparation;
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

    public function configure(): static
    {
        return $this->afterMaking(function (Preparation $preparation) {
            if (! array_key_exists('category_id', $preparation->getAttributes())) {
                $preparation->category_id = Category::where('company_id', $preparation->company_id)
                    ->value('id')
                    ?? Category::factory()->create([
                        'company_id' => $preparation->company_id,
                    ])->id;
            }
        });
    }
}
