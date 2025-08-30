<?php

namespace App\GraphQL\Queries;

use App\Models\Ingredient;

class NonPerishableIngredientsQuery
{
    public function resolve()
    {
        $companyId = auth()->user()->company_id;

        return Ingredient::with(['category.locationTypes', 'locations'])
            ->where('company_id', $companyId)
            ->get()
            ->filter(function ($ingredient) {
                return $ingredient->locations->every(function ($location) use ($ingredient) {
                    $shelfLife = $ingredient->category->locationTypes
                        ->firstWhere('id', $location->location_type_id)?->pivot->shelf_life_hours;

                    return ! $shelfLife;
                });
            });
    }
}
