<?php

namespace Database\Factories;

use App\Enums\MenuServiceType;
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
            'service_type' => $this->faker->randomElement(MenuServiceType::values()),
            'is_returnable' => $this->faker->boolean(),
            'type' => $this->faker->randomElement(['entrée', 'plat', 'dessert', 'side']),
            'price' => $this->faker->randomFloat(2, 5, 50),
        ];
    }
}
