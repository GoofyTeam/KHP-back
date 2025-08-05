<?php

namespace Database\Seeders;

use App\Models\IngredientLocation;
use App\Models\LocationPreparation;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        IngredientLocation::withoutEvents(function () {
            LocationPreparation::withoutEvents(function () {
                $this->call([
                    CompanySeeder::class,
                    UserSeeder::class,
                    CategorySeeder::class,
                    LocationSeeder::class,
                    IngredientSeeder::class,
                    PreparationSeeder::class,
                    StockMovementSeeder::class,
                ]);
            });
        });
    }
}
