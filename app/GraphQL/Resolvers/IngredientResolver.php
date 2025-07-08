<?php

namespace App\GraphQL\Resolvers;

use App\Models\Ingredient;
use Illuminate\Support\Facades\Storage;

class IngredientResolver
{
    /**
     * Generate a temporary S3 URL for the ingredient image.
     */
    public function imageUrl(Ingredient $ingredient): ?string
    {
        // Si pas d'image_url stockée, retourner null
        if (! $ingredient->image_url) {
            return null;
        }

        try {
            // Générer une URL temporaire valide pendant 1 heure (3600 secondes)
            return Storage::disk('s3')->temporaryUrl(
                $ingredient->image_url,
                now()->addHour()
            );
        } catch (\Exception $e) {
            // En cas d'erreur (fichier inexistant, etc.), retourner null
            \Log::error('Failed to generate temporary URL for ingredient image', [
                'ingredient_id' => $ingredient->id,
                'image_url' => $ingredient->image_url,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
