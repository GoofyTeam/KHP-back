<?php

namespace App\GraphQL\Queries;

use App\Models\Preparation;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

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

        $query = Preparation::forCompany()
            ->with(['locations' => function ($relation) use ($locationIds) {
                if (! empty($locationIds)) {
                    $relation->whereIn('locations.id', $locationIds);
                }
            }])
            ->whereNotNull('threshold');

        if (! empty($locationIds)) {
            $placeholders = implode(', ', array_fill(0, count($locationIds), '?'));
            $sql = sprintf(
                '(
        select coalesce(sum(lp.quantity), 0)
        from location_preparation as lp
        where lp.preparation_id = preparations.id
            and lp.location_id in (%s)
    ) < threshold',
                $placeholders
            );

            $query
                ->whereHas('locations', function ($relation) use ($locationIds) {
                    $relation->whereIn('locations.id', $locationIds);
                })
                ->whereRaw($sql, $locationIds);
        } else {
            $query->whereRaw('(
                select coalesce(sum(lp.quantity), 0)
                from location_preparation as lp
                where lp.preparation_id = preparations.id
            ) < threshold');
        }

        /** @var Collection<int, Preparation> $preparations */
        $preparations = $query->get();

        return $preparations->all();
    }
}
