<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Menu;
use Illuminate\Database\Eloquent\Factories\Factory;

class MenuFactory extends Factory
{
    protected $model = Menu::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => $this->faker->unique()->word(),
            'description' => $this->faker->sentence(),
            'image_url' => null,
            'is_a_la_carte' => $this->faker->boolean(),
            'is_available' => true,
        ];
    }
}
