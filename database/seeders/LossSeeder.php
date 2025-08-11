<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Ingredient;
use App\Models\Loss;
use App\Models\Preparation;
use Illuminate\Database\Seeder;

class LossSeeder extends Seeder
{
    /**
     * Génère des pertes factices pour chaque entreprise.
     */
    public function run(): void
    {
        Company::all()->each(function (Company $company) {
            $user = $company->users()->inRandomOrder()->first();

            $company->locations->each(function ($location) use ($company, $user) {
                // Pertes d'ingrédients
                $location->ingredients()
                    ->wherePivot('quantity', '>', 1)
                    ->take(1)
                    ->each(function (Ingredient $ingredient) use ($location, $company, $user) {
                        $qty = (float) min(1, $ingredient->pivot->quantity ?? 0);
                        $ingredient->locations()->updateExistingPivot($location->id, [
                            'quantity' => $ingredient->pivot->quantity - $qty,
                        ]);

                        Loss::create([
                            'lossable_id' => $ingredient->id,
                            'lossable_type' => Ingredient::class,
                            'location_id' => $location->id,
                            'company_id' => $company->id,
                            'user_id' => $user?->id,
                            'quantity' => $qty,
                            'reason' => 'Avarie',
                        ]);
                    });

                // Pertes de préparations
                $location->preparations()
                    ->wherePivot('quantity', '>', 1)
                    ->take(1)
                    ->each(function (Preparation $preparation) use ($location, $company, $user) {
                        $qty = (float) min(1, $preparation->pivot->quantity ?? 0);
                        $preparation->locations()->updateExistingPivot($location->id, [
                            'quantity' => $preparation->pivot->quantity - $qty,
                        ]);

                        Loss::create([
                            'lossable_id' => $preparation->id,
                            'lossable_type' => Preparation::class,
                            'location_id' => $location->id,
                            'company_id' => $company->id,
                            'user_id' => $user?->id,
                            'quantity' => $qty,
                            'reason' => 'Avarie',
                        ]);
                    });
            });
        });
    }
}
