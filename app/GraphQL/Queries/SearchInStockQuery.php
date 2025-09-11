<?php

namespace App\GraphQL\Queries;

use App\Models\Ingredient;
use App\Models\Preparation;

class SearchInStockQuery
{
    public function resolve(mixed $_, array $args): array
    {
        $keyword = $args['keyword'] ?? '';
        $ingredients = Ingredient::forCompany()
            ->search($keyword)
            ->get();

        $preparations = Preparation::forCompany()
            ->search($keyword)
            ->get();

        return $ingredients->concat($preparations)->all();
    }
}
