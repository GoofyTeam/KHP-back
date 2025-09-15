<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CompanySeeder::class,
            UserSeeder::class,
            CategorySeeder::class,
            MenuCategorySeeder::class,
            LocationSeeder::class,
            IngredientSeeder::class,
            IngredientThresholdSeeder::class,
            PerishableSeeder::class,
            PreparationSeeder::class,
            PreparationThresholdSeeder::class,
            MenuSeeder::class,
            StockMovementSeeder::class,
            LossSeeder::class,
            QuickAccessSeeder::class,
        ]);
    }
}
