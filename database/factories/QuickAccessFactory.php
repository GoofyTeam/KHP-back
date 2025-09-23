<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\QuickAccess;
use Database\Seeders\QuickAccessSeeder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\QuickAccess>
 */
class QuickAccessFactory extends Factory
{
    protected $model = QuickAccess::class;

    public function definition(): array
    {
        $options = array_values(QuickAccessSeeder::available());
        $choice = $options[array_rand($options)];

        return [
            'company_id' => Company::factory(),
            'index' => $this->faker->numberBetween(1, 5),
            'name' => $choice['name'],
            'icon' => $choice['icon'],
            'icon_color' => $choice['icon_color'],
            'url_key' => $choice['url_key'],
        ];
    }
}
