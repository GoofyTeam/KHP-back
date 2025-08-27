<?php

namespace App\GraphQL\Resolvers;

use App\Models\Category;
use App\Models\LocationType;

class CategoryResolver
{
    public function shelfLifeByLocationType(Category $category): array
    {
        $result = [];
        foreach ($category->locationTypes as $locationType) {
            /** @var LocationType $locationType */
            $result[] = [
                'locationType' => $locationType,
                'shelf_life_hours' => (int) $locationType->pivot->getAttribute('shelf_life_hours'),
            ];
        }

        return $result;
    }
}
