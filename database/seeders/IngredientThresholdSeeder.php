<?php

namespace Database\Seeders;

use App\Models\Ingredient;
use Faker\Generator as FakerGenerator;
use Illuminate\Database\Seeder;

class IngredientThresholdSeeder extends Seeder
{
    public function run(): void
    {
        $ingredients = Ingredient::query()
            ->whereNull('threshold')
            ->inRandomOrder()
            ->get();

        if ($ingredients->isEmpty()) {
            return;
        }

        $faker = fake();

        $minToUpdate = max(1, (int) ceil($ingredients->count() * 0.3));
        $countToUpdate = $faker->numberBetween($minToUpdate, $ingredients->count());

        $ingredients
            ->take($countToUpdate)
            ->each(function (Ingredient $ingredient) use ($faker) {
                $ingredient->update([
                    'threshold' => $this->generateThreshold($ingredient, $faker),
                ]);
            });
    }

    private function generateThreshold(Ingredient $ingredient, FakerGenerator $faker): float
    {
        $baseQuantity = (float) $ingredient->base_quantity;

        if ($baseQuantity > 0) {
            $min = max(0.1, $baseQuantity * 0.1);
            $max = min($baseQuantity, max($min, $baseQuantity * 0.6));
        } else {
            $min = 0.1;
            $max = 5.0;
        }

        if ($max <= $min) {
            $max = $min + 0.5;
        }

        return $faker->randomFloat(2, $min, $max);
    }
}
