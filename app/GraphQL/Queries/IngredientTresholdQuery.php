<?php

namespace App\GraphQL\Queries;

use App\Models\Ingredient;

class IngredientTresholdQuery
{
    public function resolve()
    {
        $companyId = auth()->user()->company_id;

        return Ingredient::with('locations')
            ->where('company_id', $companyId)
            ->whereNotNull('threshold')
            ->get()
            ->filter(function (Ingredient $ingredient) {
                $totalQuantity = $ingredient->locations->sum(
                    fn ($location) => (float) ($location->pivot->quantity ?? 0)
                );

                return $ingredient->threshold !== null
                    && $totalQuantity < $ingredient->threshold;
            })
            ->values();
    }
}
