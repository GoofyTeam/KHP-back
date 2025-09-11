<?php

namespace App\GraphQL\Unions;

use App\Models\Ingredient;
use App\Models\Preparation;
use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Nuwave\Lighthouse\Support\Contracts\UnionType;

class SearchItem implements UnionType
{
    public function __construct(private TypeRegistry $typeRegistry) {}

    public function resolveType(mixed $value): Type
    {
        return match (true) {
            $value instanceof Ingredient => $this->typeRegistry->get('Ingredient'),
            $value instanceof Preparation => $this->typeRegistry->get('Preparation'),
            default => null,
        };
    }
}
