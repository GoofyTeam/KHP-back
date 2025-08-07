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
        Ingredient::whereHas('locations', fn ($q) => $q->where('quantity', '>', 0))
            ->get()
            ->each(function ($ingredient) {
                $ingredient->locations->each(function ($location) use ($ingredient) {
                    $currentQuantity = $location->pivot->quantity;

                    if ($currentQuantity < 3) {
                        return; // on ignore les trop petites quantités
                    }

                    // -----------------------
                    // 1) ADDITIONS
                    // -----------------------
                    $numAdd = rand(3, 5);
                    $startDate = Carbon::now()->subDays(30);
                    $running = 0;

                    for ($i = 0; $i < $numAdd; $i++) {
                        $portion = $currentQuantity / ($numAdd + 1);
                        $addAmount = $portion * (0.5 + rand(0, 100) / 100);

                        $old = $running;
                        $running += $addAmount;

                        $ingredient->stockMovements()->create([
                            'location_id' => $location->id,
                            'company_id' => $ingredient->company_id,
                            'user_id' => null,
                            'type' => 'addition',
                            'quantity' => round($addAmount, 2),
                            'quantity_before' => round($old, 2),
                            'quantity_after' => round($running, 2),
                            'created_at' => $startDate,
                            'updated_at' => $startDate,
                        ]);

                        // avancer la date
                        $startDate->addDays(rand(1, 5));
                    }

                    // Ajustement final (addition ou retrait)
                    if (abs($running - $currentQuantity) > 0.01) {
                        $diffType = $running < $currentQuantity ? 'addition' : 'withdrawal';
                        $diffAmt = abs($currentQuantity - $running);
                        $ingredient->stockMovements()->create([
                            'location_id' => $location->id,
                            'company_id' => $ingredient->company_id,
                            'user_id' => null,
                            'type' => $diffType,
                            'quantity' => round($diffAmt, 2),
                            'quantity_before' => round($running, 2),
                            'quantity_after' => round($currentQuantity, 2),
                            'created_at' => $startDate,
                            'updated_at' => $startDate,
                        ]);
                        $running = $currentQuantity;
                    }

                    // -----------------------
                    // 2) WITHDRAWALS
                    // -----------------------
                    // on crée 1 à 3 retraits échelonnés sur les 30 derniers jours
                    $withdrawCount = rand(1, 3);
                    $withdrawDates = [];
                    for ($i = 0; $i < $withdrawCount; $i++) {
                        // date aléatoire entre -30j et aujourd'hui
                        $withdrawDates[] = Carbon::now()->subDays(rand(0, 30));
                    }
                    sort($withdrawDates);

                    foreach ($withdrawDates as $wDate) {
                        // on ne retire jamais plus que le stock courant
                        if ($running < 1) {
                            break;
                        }
                        // quantité aléatoire entre 1 et running
                        $amt = rand(1, floor($running));
                        $old = $running;
                        $running -= $amt;

                        $ingredient->stockMovements()->create([
                            'location_id' => $location->id,
                            'company_id' => $ingredient->company_id,
                            'user_id' => null,
                            'type' => 'withdrawal',
                            'quantity' => round($amt, 2),
                            'quantity_before' => round($old, 2),
                            'quantity_after' => round($running, 2),
                            'created_at' => $wDate,
                            'updated_at' => $wDate,
                        ]);
                    }
                });
            });
    }

    private function seedPreparationMovements(): void
    {
        Preparation::whereHas('locations', fn ($q) => $q->where('quantity', '>', 0))
            ->get()
            ->each(function ($prep) {
                $prep->locations->each(function ($location) use ($prep) {
                    $current = $location->pivot->quantity;

                    if ($current < 2) {
                        return;
                    }

                    // ADDITIONS
                    $numAdd = rand(2, 4);
                    $date = Carbon::now()->subDays(20);
                    $running = 0;

                    for ($i = 0; $i < $numAdd; $i++) {
                        $portion = $current / ($numAdd + 1);
                        $addAmount = $portion * (0.5 + rand(0, 100) / 100);
                        $old = $running;
                        $running += $addAmount;

                        $prep->stockMovements()->create([
                            'location_id' => $location->id,
                            'company_id' => $prep->company_id,
                            'user_id' => null,
                            'type' => 'addition',
                            'quantity' => round($addAmount, 2),
                            'quantity_before' => round($old, 2),
                            'quantity_after' => round($running, 2),
                            'created_at' => $date,
                            'updated_at' => $date,
                        ]);

                        $date->addDays(rand(1, 4));
                    }

                    // Ajustement final
                    if (abs($running - $current) > 0.01) {
                        $type = $running < $current ? 'addition' : 'withdrawal';
                        $qty = abs($current - $running);
                        $prep->stockMovements()->create([
                            'location_id' => $location->id,
                            'company_id' => $prep->company_id,
                            'user_id' => null,
                            'type' => $type,
                            'quantity' => round($qty, 2),
                            'quantity_before' => round($running, 2),
                            'quantity_after' => round($current, 2),
                            'created_at' => $date,
                            'updated_at' => $date,
                        ]);
                        $running = $current;
                    }

                    // WITHDRAWALS
                    $wCount = rand(1, 2);
                    $wDates = [];
                    for ($i = 0; $i < $wCount; $i++) {
                        $wDates[] = Carbon::now()->subDays(rand(0, 20));
                    }
                    sort($wDates);

                    foreach ($wDates as $wDate) {
                        if ($running < 1) {
                            break;
                        }
                        $amt = rand(1, floor($running));
                        $old = $running;
                        $running -= $amt;

                        $prep->stockMovements()->create([
                            'location_id' => $location->id,
                            'company_id' => $prep->company_id,
                            'user_id' => null,
                            'type' => 'withdrawal',
                            'quantity' => round($amt, 2),
                            'quantity_before' => round($old, 2),
                            'quantity_after' => round($running, 2),
                            'created_at' => $wDate,
                            'updated_at' => $wDate,
                        ]);
                    }
                });
            });
    }
}
