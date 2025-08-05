<?php

namespace Database\Seeders;

use App\Models\Ingredient;
use App\Models\Preparation;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class StockMovementSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedIngredientMovements();
        $this->seedPreparationMovements();
    }

    private function seedIngredientMovements(): void
    {
        // Récupérer les ingrédients qui ont du stock
        Ingredient::whereHas('locations', function ($query) {
            $query->where('quantity', '>', 0);
        })->get()->each(function ($ingredient) {
            $ingredient->locations()->get()->each(function ($location) use ($ingredient) {
                $currentQuantity = $location->pivot->quantity;

                // Uniquement pour les quantités significatives
                if ($currentQuantity >= 3) {
                    // Créer 3-5 mouvements dans le passé
                    $numMovements = rand(3, 5);
                    $date = Carbon::now()->subDays(30);

                    // Partir de zéro et augmenter progressivement
                    $runningQuantity = 0;

                    for ($i = 0; $i < $numMovements; $i++) {
                        // Calcul d'une portion de la quantité finale
                        $portion = $currentQuantity / ($numMovements + 1);
                        // Ajouter une portion avec une variation aléatoire
                        $addAmount = $portion * (0.5 + (rand(0, 100) / 100));

                        $oldQty = $runningQuantity;
                        $runningQuantity += $addAmount;

                        // Créer le mouvement
                        $ingredient->stockMovements()->create([
                            'location_id' => $location->id,
                            'company_id' => $ingredient->company_id,
                            'user_id' => null,
                            'type' => 'addition',
                            'quantity' => $addAmount,
                            'quantity_before' => $oldQty,
                            'quantity_after' => $runningQuantity,
                            'created_at' => $date,
                            'updated_at' => $date,
                        ]);

                        // Avancer de 1-5 jours
                        $date = $date->addDays(rand(1, 5));
                    }

                    // Dernier mouvement pour atteindre la quantité actuelle
                    if ($runningQuantity != $currentQuantity) {
                        $ingredient->stockMovements()->create([
                            'location_id' => $location->id,
                            'company_id' => $ingredient->company_id,
                            'user_id' => null,
                            'type' => $runningQuantity < $currentQuantity ? 'addition' : 'withdrawal',
                            'quantity' => abs($currentQuantity - $runningQuantity),
                            'quantity_before' => $runningQuantity,
                            'quantity_after' => $currentQuantity,
                            'created_at' => $date,
                            'updated_at' => $date,
                        ]);
                    }
                }
            });
        });
    }

    private function seedPreparationMovements(): void
    {
        // Même logique simplifiée pour les préparations
        Preparation::whereHas('locations', function ($query) {
            $query->where('quantity', '>', 0);
        })->get()->each(function ($preparation) {
            $preparation->locations()->get()->each(function ($location) use ($preparation) {
                $currentQuantity = $location->pivot->quantity;

                if ($currentQuantity >= 2) {
                    $numMovements = rand(2, 4);
                    $date = Carbon::now()->subDays(20);
                    $runningQuantity = 0;

                    for ($i = 0; $i < $numMovements; $i++) {
                        $portion = $currentQuantity / ($numMovements + 1);
                        $addAmount = $portion * (0.5 + (rand(0, 100) / 100));

                        $oldQty = $runningQuantity;
                        $runningQuantity += $addAmount;

                        $preparation->stockMovements()->create([
                            'location_id' => $location->id,
                            'company_id' => $preparation->company_id,
                            'user_id' => null,
                            'type' => 'addition',
                            'quantity' => $addAmount,
                            'quantity_before' => $oldQty,
                            'quantity_after' => $runningQuantity,
                            'created_at' => $date,
                            'updated_at' => $date,
                        ]);

                        $date = $date->addDays(rand(1, 4));
                    }

                    if ($runningQuantity != $currentQuantity) {
                        $preparation->stockMovements()->create([
                            'location_id' => $location->id,
                            'company_id' => $preparation->company_id,
                            'user_id' => null,
                            'type' => $runningQuantity < $currentQuantity ? 'addition' : 'withdrawal',
                            'quantity' => abs($currentQuantity - $runningQuantity),
                            'quantity_before' => $runningQuantity,
                            'quantity_after' => $currentQuantity,
                            'created_at' => $date,
                            'updated_at' => $date,
                        ]);
                    }
                }
            });
        });
    }
}
