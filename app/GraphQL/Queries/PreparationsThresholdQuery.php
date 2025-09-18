<?php

namespace App\GraphQL\Queries;

use App\Models\Preparation;
use Illuminate\Support\Arr;

class PreparationsThresholdQuery
{
    /**
     * Retourne les préparations dont le stock total est inférieur à leur seuil configuré.
     *
     * @return array<int, \App\Models\Preparation>
     */
    public function resolve(mixed $_, array $args): array
    {
        $locationIds = array_values(
            Arr::where(
                Arr::wrap($args['locationIds'] ?? []),
                fn ($value) => $value !== null && $value !== ''
            )
        );

        $preparations = Preparation::forCompany()
            ->with(['locations' => function ($relation) use ($locationIds) {
                if (! empty($locationIds)) {
                    $relation->whereIn('locations.id', $locationIds);
                }
            }])
            ->whereNotNull('threshold')
            ->when(! empty($locationIds), function ($query) use ($locationIds) {
                $query->whereHas('locations', function ($locationQuery) use ($locationIds) {
                    $locationQuery->whereIn('locations.id', $locationIds);
                });
            })
            ->get();

        return $preparations
            ->filter(function (Preparation $preparation): bool {
                $totalQuantity = $preparation->locations->sum(
                    fn ($location) => (float) ($location->pivot->quantity ?? 0)
                );

                return $preparation->threshold !== null
                    && $totalQuantity < $preparation->threshold;
            })
            ->values()
            ->all();
    }
}
