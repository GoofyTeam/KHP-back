<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Ingredient;
use App\Models\IngredientImage;
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

        // Récupérer toutes les entreprises
        $companies = Company::all();

        // Créer des images pour chaque ingrédient
        foreach ($ingredients as $ingredient) {
            // Pour chaque entreprise, créer une image personnalisée
            foreach ($companies as $company) {
                // Générer une image aléatoire avec picsum.photos
                // Utiliser différentes tailles pour variété (entre 200x200 et 500x500)
                $width = rand(200, 500);
                $height = rand(200, 500);
                $image_url = "https://picsum.photos/$width/$height?random=".rand(1, 1000);

                // Créer l'enregistrement dans la table ingredient_images
                IngredientImage::create([
                    'ingredient_id' => $ingredient->id,
                    'company_id' => $company->id,
                    'image_url' => $image_url,
                ]);
            }
        }

        // Attribuer des ingrédients aux emplacements de GoofyTeam
        $company = Company::where('name', 'GoofyTeam')->first();
        $locations = Location::where('company_id', $company->id)->get();

        foreach ($locations as $location) {
            // Ajouter 5-10 ingrédients à chaque emplacement avec des quantités aléatoires
            $ingredientsForLocation = $ingredients->random(rand(5, 10));

            foreach ($ingredientsForLocation as $ingredient) {
                $location->ingredients()->attach($ingredient->id, [
                    'quantity' => rand(1, 100),
                    'use_default_image' => (bool) rand(0, 1), // Ajouter l'option d'utiliser l'image par défaut ou non
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
                        'use_default_image' => (bool) rand(0, 1), // Ajouter l'option d'utiliser l'image par défaut ou non
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }
}
