<?php

namespace App\Providers;

use App\Models\IngredientLocation;
use App\Models\LocationPreparation;
use App\Observers\IngredientLocationObserver;
use App\Observers\LocationPreparationObserver;
use App\Services\OpenFoodFactsService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->singleton(OpenFoodFactsService::class);
        IngredientLocation::observe(IngredientLocationObserver::class);
        LocationPreparation::observe(LocationPreparationObserver::class);
    }
}
