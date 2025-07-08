<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Ingredient;
use App\Models\Location;
use Illuminate\Database\Seeder;

class IngredientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Créer 30 ingrédients
        $ingredients = Ingredient::factory()->count(30)->create();

        // Attribuer des ingrédients aux emplacements de GoofyTeam
        $company = Company::where('name', 'GoofyTeam')->first();
        $locations = Location::where('company_id', $company->id)->get();

        foreach ($locations as $location) {
            // Ajouter 5-10 ingrédients à chaque emplacement avec des quantités aléatoires
            $ingredientsForLocation = $ingredients->random(rand(5, 10));

            foreach ($ingredientsForLocation as $ingredient) {
                $location->ingredients()->attach($ingredient->id, [
                    'quantity' => rand(1, 100),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Attribuer quelques ingrédients aux emplacements des autres entreprises
        $otherCompanies = Company::where('name', '!=', 'GoofyTeam')->get();
        foreach ($otherCompanies as $company) {
            $locations = Location::where('company_id', $company->id)->get();

            foreach ($locations as $location) {
                // Ajouter 3-5 ingrédients à chaque emplacement
                $ingredientsForLocation = $ingredients->random(rand(3, 5));

                foreach ($ingredientsForLocation as $ingredient) {
                    $location->ingredients()->attach($ingredient->id, [
                        'quantity' => rand(1, 50),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }
}
