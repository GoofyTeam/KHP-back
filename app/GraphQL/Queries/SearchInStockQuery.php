<?php

namespace App\GraphQL\Queries;

use App\Models\Ingredient;
use App\Models\Preparation;

class SearchInStockQuery
{
    /**
     * Search ingredients and preparations by keyword.
     *
     * @param  array<string,mixed>  $args
     * @return array<int, array<string, mixed>>
     */
    public function resolve(mixed $_, array $args): array
    {
        $keyword = $args['keyword'] ?? '';

        $ingredients = Ingredient::forCompany()
            ->search($keyword)
            ->get()
            ->map(fn ($ingredient) => [
                'type' => 'ingredient',
                'ingredient' => $ingredient,
            ]);

        $preparations = Preparation::forCompany()
            ->search($keyword)
            ->get()
            ->map(fn ($preparation) => [
                'type' => 'preparation',
                'preparation' => $preparation,
            ]);

        return $ingredients->concat($preparations)->all();
    }
}
