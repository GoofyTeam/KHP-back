<?php

namespace App\Providers;

use App\Enums\Allergen;
use App\Enums\MeasurementUnit;
use App\Enums\MenuServiceType;
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

        // Enregistre l'enum PHP directement dans Lighthouse TypeRegistry
        $typeRegistry = app(TypeRegistry::class);

        $typeRegistry->register(new EnumType([
            'name' => 'UnitEnum',
            'values' => collect(MeasurementUnit::cases())
                ->mapWithKeys(fn ($c) => [
                    $c->value => ['value' => $c],
                ])->all(),
        ]));

        $typeRegistry->register(new EnumType([
            'name' => 'AllergenEnum',
            'values' => collect(Allergen::cases())
                ->mapWithKeys(fn ($c) => [
                    $c->value => ['value' => $c->value],
                ])->all(),
        ]));

        $typeRegistry->register(new EnumType([
            'name' => 'MenuServiceTypeEnum',
            'values' => collect(MenuServiceType::cases())
                ->mapWithKeys(fn ($c) => [
                    $c->value => ['value' => $c],
                ])->all(),
        ]));
    }
}
