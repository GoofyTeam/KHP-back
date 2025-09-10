<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\MenuCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuCategory>
 */
class MenuCategoryFactory extends Factory
{
    protected $model = MenuCategory::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word(),
            'company_id' => Company::factory(),
        ];
    }
}
