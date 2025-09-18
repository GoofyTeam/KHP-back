<?php

namespace App\GraphQL\Queries;

use App\Models\Ingredient;
use Illuminate\Support\Arr;

class IngredientTresholdQuery
{
    public function resolve(mixed $_, array $args)
    {
        $companyId = auth()->user()->company_id;
        $locationIds = array_values(
            Arr::where(
                Arr::wrap($args['locationIds'] ?? []),
                fn ($value) => $value !== null && $value !== ''
            )
        );

        $ingredients = Ingredient::with(['locations' => function ($query) use ($locationIds) {
            if (! empty($locationIds)) {
                $query->whereIn('locations.id', $locationIds);
            }
        }])
            ->where('company_id', $companyId)
            ->whereNotNull('threshold')
            ->when(! empty($locationIds), function ($query) use ($locationIds) {
                $query->whereHas('locations', function ($locationQuery) use ($locationIds) {
                    $locationQuery->whereIn('locations.id', $locationIds);
                });
            })
            ->get();

        return $ingredients
            ->filter(function (Ingredient $ingredient) {
                $totalQuantity = $ingredient->locations->sum(
                    fn ($location) => (float) ($location->pivot->quantity ?? 0)
                );

                return $ingredient->threshold !== null
                    && $totalQuantity < $ingredient->threshold;
            })
            ->values()
            ->all();
    }
}
