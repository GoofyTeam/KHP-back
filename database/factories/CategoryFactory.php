<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Company;
use App\Models\LocationType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word(),
            'company_id' => Company::factory(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Category $category) {
            $companyId = $category->company_id;

            $freezer = LocationType::firstOrCreate(
                ['company_id' => $companyId, 'name' => 'Congélateur'],
                ['is_default' => true]
            );
            $fridge = LocationType::firstOrCreate(
                ['company_id' => $companyId, 'name' => 'Réfrigérateur'],
                ['is_default' => true]
            );

            $category->locationTypes()->attach([
                $fridge->id => ['shelf_life_hours' => 24],
                $freezer->id => ['shelf_life_hours' => 168],
            ]);
        });
    }
}
