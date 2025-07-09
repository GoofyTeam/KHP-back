<?php

namespace App\GraphQL\Resolvers;

use App\Models\Ingredient;

class IngredientResolver
{
    /**
     * Generate a temporary S3 URL for the ingredient image.
     */
    public function imageUrl(Ingredient $ingredient): ?string
    {
        if (! $ingredient->image_url) {
            return null;
        }

        return url('/api/image-proxy/'.$ingredient->image_url);
    }
}
