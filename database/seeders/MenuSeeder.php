<?php

namespace Database\Seeders;

use App\Enums\MeasurementUnit;
use App\Models\Company;
use App\Models\Ingredient;
use App\Models\Location;
use App\Models\Menu;
use App\Models\MenuItem;
use Illuminate\Database\Seeder;

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

            for ($i = 0; $i < 5; $i++) {
                $menu = Menu::factory()->create([
                    'company_id' => $company->id,
                ]);

                if ($ingredients->count() === 0 || $locations->count() === 0) {
                    $menu->refreshAvailability();

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

                $menu->refreshAvailability();
            }
        }
    }
}
