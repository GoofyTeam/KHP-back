<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Ingredient;
use App\Models\Location;
use App\Models\Loss;
use Illuminate\Database\Seeder;

class LossSeeder extends Seeder
{
    public function run()
    {
        $companies = Company::all();

        foreach ($companies as $company) {
            $locations = Location::where('company_id', $company->id)->get();
            $ingredients = Ingredient::where('company_id', $company->id)->get();

            if ($locations->isEmpty() || $ingredients->isEmpty()) {
                continue;
            }

            // create some random losses
            foreach (range(1, 10) as $i) {
                $ingredient = $ingredients->random();
                $location = $locations->random();

                Loss::factory()->create([
                    'company_id' => $company->id,
                    'entity_type' => \App\Models\Ingredient::class,
                    'entity_id' => $ingredient->id,
                    'location_id' => $location->id,
                ]);
            }
        }
    }
}
