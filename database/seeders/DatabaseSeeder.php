<?php

namespace Database\Seeders;

use App\Services\ImageService;
use Illuminate\Database\Seeder;
use Throwable;

class DatabaseSeeder extends Seeder
{
    public function __construct(private readonly ImageService $imageService) {}

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->publishDefaultPlaceholder();

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
            OrderSeeder::class,
            OrderStepSeeder::class,
            StepMenuSeeder::class,
            OrderHistorySeeder::class,
        ]);
    }

    private function publishDefaultPlaceholder(): void
    {
        $source = storage_path('app/private/images/placeholder.svg');

        try {
            $this->imageService->storeLocalImage($source, 'private/images/placeholder.svg');
        } catch (Throwable $exception) {
            if ($this->command) {
                $this->command->warn('Impossible de publier le placeholder par défaut : '.$exception->getMessage());
            }
        }
    }
}
