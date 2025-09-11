<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\QuickAccess;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\QuickAccess>
 */
class QuickAccessFactory extends Factory
{
    protected $model = QuickAccess::class;

    public function definition(): array
    {
        $icons = ['Plus', 'Notebook', 'Minus', 'Calendar', 'Check'];
        $color = ['primary', 'warning', 'error', 'info'];
        $url = ['add_to_stock', 'menu_card', 'stock', 'take_order'];

        return [
            'company_id' => Company::factory(),
            'index' => $this->faker->numberBetween(1, 4),
            'name' => $this->faker->words(2, true),
            'icon' => $this->faker->randomElement($icons),
            'icon_color' => $this->faker->randomElement($color),
            'url_key' => $this->faker->randomElement($url),
        ];
    }
}
