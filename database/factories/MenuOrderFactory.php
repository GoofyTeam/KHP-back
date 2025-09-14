<?php

namespace Database\Factories;

use App\Models\Menu;
use App\Models\MenuOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

class MenuOrderFactory extends Factory
{
    protected $model = MenuOrder::class;

    public function definition(): array
    {
        return [
            'menu_id' => Menu::factory(),
            'status' => $this->faker->randomElement(['pending', 'completed', 'canceled']),
            'quantity' => $this->faker->numberBetween(1, 5),
        ];
    }
}
