<?php

namespace App\GraphQL\Unions;

use App\Models\Ingredient;
use App\Models\Preparation;

class SearchResultUnion
{
    /**
     * Resolve the GraphQL type name for the union value.
     */
    public function resolveType(mixed $value): ?string
    {
        if ($value instanceof Ingredient) {
            return 'Ingredient';
        }

        if ($value instanceof Preparation) {
            return 'Preparation';
        }

        return null;
    }
}

