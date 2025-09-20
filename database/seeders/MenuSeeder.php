<?php

namespace Database\Seeders;

use App\Enums\MeasurementUnit;
use App\Enums\MenuServiceType;
use App\Models\Company;
use App\Models\Ingredient;
use App\Models\Location;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $companies = Company::all();

        foreach ($companies as $company) {
            $ingredients = Ingredient::where('company_id', $company->id)
                ->whereHas('locations')
                ->get();
            $locations = Location::where('company_id', $company->id)->get();
            $categories = MenuCategory::where('company_id', $company->id)->get();
            $menuTypeIds = MenuType::where('company_id', $company->id)->pluck('id')->all();

            if (empty($menuTypeIds)) {
                $defaultTypes = [
                    ['name' => 'Entrées', 'position' => 0],
                    ['name' => 'Plats', 'position' => 1],
                    ['name' => 'Desserts', 'position' => 2],
                    ['name' => 'Accompagnements', 'position' => 3],
                ];

                foreach ($defaultTypes as $type) {
                    $menuType = MenuType::firstOrCreate(
                        [
                            'company_id' => $company->id,
                            'name' => $type['name'],
                        ]
                    );

                    $menuType->publicOrder()->updateOrCreate(
                        ['company_id' => $company->id],
                        ['position' => $type['position']]
                    );

                    $menuTypeIds[] = $menuType->id;
                }
            }

            if (empty($menuTypeIds)) {
                continue;
            }

            $priorityPerType = array_fill_keys($menuTypeIds, 0);

            for ($i = 0; $i < 5; $i++) {
                $menuTypeId = Arr::random($menuTypeIds);
                $priority = $priorityPerType[$menuTypeId] ?? 0;
                $priorityPerType[$menuTypeId] = $priority + 1;

                $menu = Menu::factory()->create([
                    'company_id' => $company->id,
                    'service_type' => fake()->randomElement(MenuServiceType::values()),
                    'is_returnable' => fake()->boolean(),
                    'menu_type_id' => $menuTypeId,
                    'public_priority' => $priority,
                    'price' => fake()->randomFloat(2, 5, 50),
                ]);

                if ($categories->count() > 0) {
                    $menu->categories()->sync(
                        $categories->random(rand(0, min(3, $categories->count())))->pluck('id')->toArray()
                    );
                }

                if ($ingredients->count() === 0 || $locations->count() === 0) {
                    continue;
                }

                $selected = $ingredients->random(min(2, $ingredients->count()));
                foreach ($selected as $ingredient) {
                    $location = $ingredient->locations()->inRandomOrder()->first();
                    MenuItem::create([
                        'menu_id' => $menu->id,
                        'entity_id' => $ingredient->id,
                        'entity_type' => Ingredient::class,
                        'quantity' => 1,
                        'unit' => $ingredient->unit instanceof MeasurementUnit ? $ingredient->unit->value : $ingredient->unit,
                        'location_id' => $location->id,
                    ]);
                }

            }
        }
    }
}
