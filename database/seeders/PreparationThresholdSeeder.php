<?php

namespace Database\Seeders;

use App\Models\Preparation;
use Faker\Generator as FakerGenerator;
use Illuminate\Database\Seeder;

class PreparationThresholdSeeder extends Seeder
{
    public function run(): void
    {
        $preparations = Preparation::query()
            ->whereNull('threshold')
            ->inRandomOrder()
            ->get();

        if ($preparations->isEmpty()) {
            return;
        }

        $faker = fake();

        $minToUpdate = max(1, (int) ceil($preparations->count() * 0.3));
        $countToUpdate = $faker->numberBetween($minToUpdate, $preparations->count());

        $preparations
            ->take($countToUpdate)
            ->each(function (Preparation $preparation) use ($faker) {
                $preparation->update([
                    'threshold' => $this->generateThreshold($preparation, $faker),
                ]);
            });
    }

    private function generateThreshold(Preparation $preparation, FakerGenerator $faker): float
    {
        $baseQuantity = (float) $preparation->base_quantity;

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
