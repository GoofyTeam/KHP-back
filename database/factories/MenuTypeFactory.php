<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\MenuType;
use App\Models\MenuTypePublicOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuType>
 */
class MenuTypeFactory extends Factory
{
    protected $model = MenuType::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => $this->faker->unique()->words(2, true),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (MenuType $menuType) {
            MenuTypePublicOrder::create([
                'menu_type_id' => $menuType->id,
                'company_id' => $menuType->company_id,
                'position' => 0,
            ]);
        });
    }
}
