<?php

namespace App\Providers;

use App\Enums\MeasurementUnit;
use App\Models\IngredientLocation;
use App\Models\LocationPreparation;
use App\Observers\IngredientLocationObserver;
use App\Observers\LocationPreparationObserver;
use App\Services\OpenFoodFactsService;
use GraphQL\Type\Definition\EnumType;
use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\Schema\TypeRegistry;

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

        // Enregistre l'enum PHP directement dans Lighthouse TypeRegistry
        $typeRegistry = app(TypeRegistry::class);

        $typeRegistry->register(new EnumType([
            'name' => 'UnitEnum',
            'values' => collect(MeasurementUnit::cases())
                ->mapWithKeys(fn ($c) => [
                    $c->value => ['value' => $c], // literal name = "dL", internal value = enum case
                ])->all(),
        ]));
    }
}
