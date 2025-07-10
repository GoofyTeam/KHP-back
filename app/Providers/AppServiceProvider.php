<?php

namespace App\Providers;

use App\Enums\PreparationTypeEnum;
use App\Services\OpenFoodFactsService;
use GraphQL\Type\Definition\PhpEnumType;
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
        /** @var TypeRegistry $typeRegistry */
        $typeRegistry = $this->app->make(TypeRegistry::class);

        // This will expose a GraphQL enum called "PreparationTypeEnum"
        $typeRegistry->register(new PhpEnumType(PreparationTypeEnum::class));

        $this->app->singleton(OpenFoodFactsService::class);
    }
}
