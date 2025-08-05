<?php

namespace Database\Seeders;

use App\Models\IngredientLocation;
use App\Models\LocationPreparation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Event;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Désactiver les listeners d'événements pour éviter que les observers
        // ne créent automatiquement des mouvements de stock
        Event::fake([
            'eloquent.created: '.IngredientLocation::class,
            'eloquent.updated: '.IngredientLocation::class,
            'eloquent.deleted: '.IngredientLocation::class,
            'eloquent.created: '.LocationPreparation::class,
            'eloquent.updated: '.LocationPreparation::class,
            'eloquent.deleted: '.LocationPreparation::class,
        ]);

        $this->call([
            CompanySeeder::class,
            UserSeeder::class,
            CategorySeeder::class,
            LocationSeeder::class,
            IngredientSeeder::class,
            PreparationSeeder::class,
            StockMovementSeeder::class,
        ]);
    }
}
