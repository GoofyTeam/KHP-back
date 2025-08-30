<?php

namespace App\GraphQL\Queries;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Location;

class NonPerishableIngredientsQuery
{
    public function resolve()
    {
        $companyId = auth()->user()->company_id;

        return Ingredient::with(['category.locationTypes', 'locations'])
            ->where('company_id', $companyId)
            ->get()
            ->filter(function (Ingredient $ingredient) {
                /** @var Category|null $category */
                $category = $ingredient->category;

                return $ingredient->locations->every(function ($location) use ($category) {
                    /** @var Location $location */
                    $location = $location;

                    $locationType = $category?->locationTypes
                        ->firstWhere('id', $location->location_type_id);

                    $shelfLife = $locationType?->getRelationValue('pivot')
                        ?->getAttribute('shelf_life_hours');

                    return ! $shelfLife;
                });
            });
    }
}
