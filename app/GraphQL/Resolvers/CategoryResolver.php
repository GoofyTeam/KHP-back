<?php

namespace App\GraphQL\Resolvers;

use App\Models\Category;

class CategoryResolver
{
    public function shelfLifeByLocationType(Category $category): array
    {
        $result = [];
        foreach ($category->locationTypes as $locationType) {
            $result[] = [
                'locationType' => $locationType,
                'shelf_life_hours' => $locationType->pivot->shelf_life_hours,
            ];
        }

        return $result;
    }
}
