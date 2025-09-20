<?php

namespace Database\Factories;

use App\Enums\MenuServiceType;
use App\Models\Company;
use App\Models\Menu;
use App\Models\MenuType;
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
            'menu_type_id' => function (array $attributes) {
                $companyId = $attributes['company_id'] ?? null;

                if ($companyId instanceof Company) {
                    $companyId = $companyId->id;
                }

                if (! $companyId) {
                    $companyId = Company::factory()->create()->id;
                }

                return MenuType::factory()->create([
                    'company_id' => $companyId,
                ])->id;
            },
            'service_type' => $this->faker->randomElement(MenuServiceType::values()),
            'is_returnable' => $this->faker->boolean(),
            'public_priority' => $this->faker->numberBetween(0, 20),
            'price' => $this->faker->randomFloat(2, 5, 50),
        ];
    }
}
