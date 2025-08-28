<?php

namespace App\GraphQL\Resolvers;

use App\Models\Ingredient;
use App\Models\Loss;
use App\Models\Preparation;
use Carbon\Carbon;

class LossesResolver
{
    /**
     * Retourne les statistiques des pertes pour la période donnée.
     *
     * @param  array<string, mixed>  $args
     * @return array<string, float>
     */
    public function stats(array $args): array
    {
        $start = isset($args['start_date'])
            ? Carbon::parse($args['start_date'])
            : now()->subWeek();

        $end = isset($args['end_date'])
            ? Carbon::parse($args['end_date'])
            : now();

        $ingredient = Loss::forCompany()
            ->whereBetween('created_at', [$start, $end])
            ->where('lossable_type', Ingredient::class)
            ->sum('quantity');

        $preparation = Loss::forCompany()
            ->whereBetween('created_at', [$start, $end])
            ->where('lossable_type', Preparation::class)
            ->sum('quantity');

        return [
            'ingredient' => (float) $ingredient,
            'preparation' => (float) $preparation,
            'total' => (float) ($ingredient + $preparation),
        ];
    }
}
