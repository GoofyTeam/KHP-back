<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Ingredient;
use App\Models\Location;
use App\Models\Preparation;
use App\Models\PreparationEntity;
use Illuminate\Database\Seeder;

class PreparationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $company = Company::where('name', 'GoofyTeam')->first();

        // Crée 40 préparations pour GoofyTeam
        $preparations = Preparation::factory()
            ->count(40)
            ->create([
                'company_id' => $company->id,
            ]);

        // Récupère quelques ingrédients de la société
        $ingredients = Ingredient::where('company_id', $company->id)->inRandomOrder()->take(20)->get();

        // Récupère les emplacements de la société
        $locations = Location::where('company_id', $company->id)->get();

        // Pour chaque préparation, lie 2 à 4 ingrédients ET éventuellement une nouvelle préparation
        // Et ajoute des quantités pour 1 à 3 emplacements
        foreach ($preparations as $preparation) {
            // Ajoute des ingrédients
            $usedIngredients = $ingredients->random(rand(2, 4));
            foreach ($usedIngredients as $ingredient) {
                PreparationEntity::create([
                    'preparation_id' => $preparation->id,
                    'entity_id' => $ingredient->id,
                    'entity_type' => Ingredient::class,
                ]);
            }

            // Ajoute éventuellement une nouvelle préparation comme entité (jamais déjà liée)
            if ($preparations->count() > 1 && rand(0, 1)) {
                // On ne prend que les préparations qui ne sont pas déjà liées à celle-ci
                $alreadyLinkedIds = PreparationEntity::where('preparation_id', $preparation->id)
                    ->where('entity_type', Preparation::class)
                    ->pluck('entity_id')
                    ->toArray();

                $possiblePreparations = $preparations
                    ->where('id', '!=', $preparation->id)
                    ->whereNotIn('id', $alreadyLinkedIds);

                if ($possiblePreparations->count() > 0) {
                    $newPreparation = $possiblePreparations->random();
                    PreparationEntity::create([
                        'preparation_id' => $preparation->id,
                        'entity_id' => $newPreparation->id,
                        'entity_type' => Preparation::class,
                    ]);
                }
            }

            // Ajoute des quantités pour 1 à 3 emplacements aléatoires
            if ($locations->count() > 0) {
                $usedLocations = $locations->random(min($locations->count(), rand(1, 3)));
                $locationData = [];

                foreach ($usedLocations as $location) {
                    $locationData[$location->id] = [
                        'quantity' => rand(1, 20) + (rand(0, 100) / 100), // Génère un nombre entre 1.00 et 20.99
                    ];
                }

                // Attache les emplacements avec leurs quantités
                $preparation->locations()->attach($locationData);
            }
        }

        // Pour les autres sociétés
        $otherCompanies = Company::where('name', '!=', 'GoofyTeam')->get();

        foreach ($otherCompanies as $company) {
            $preparations = Preparation::factory()
                ->count(5)
                ->create([
                    'company_id' => $company->id,
                ]);
            $ingredients = Ingredient::where('company_id', $company->id)->inRandomOrder()->take(10)->get();
            $locations = Location::where('company_id', $company->id)->get();

            foreach ($preparations as $preparation) {
                // Ajoute des ingrédients
                $usedIngredients = $ingredients->random(rand(2, 3));
                foreach ($usedIngredients as $ingredient) {
                    PreparationEntity::create([
                        'preparation_id' => $preparation->id,
                        'entity_id' => $ingredient->id,
                        'entity_type' => Ingredient::class,
                    ]);
                }

                // Ajoute des quantités pour 1 à 2 emplacements aléatoires
                if ($locations->count() > 0) {
                    $usedLocations = $locations->random(min($locations->count(), rand(1, 2)));
                    $locationData = [];

                    foreach ($usedLocations as $location) {
                        $locationData[$location->id] = [
                            'quantity' => rand(1, 10) + (rand(0, 100) / 100), // Génère un nombre entre 1.00 et 10.99
                        ];
                    }

                    // Attache les emplacements avec leurs quantités
                    $preparation->locations()->attach($locationData);
                }
            }
        }
    }
}
