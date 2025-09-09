<?php

namespace App\GraphQL\Queries;

use App\Enums\Allergen;

class AllergensQuery
{
    /**
     * Retourne la liste des allergènes disponibles
     */
    public function resolve(): array
    {
        return Allergen::values();
    }
}
