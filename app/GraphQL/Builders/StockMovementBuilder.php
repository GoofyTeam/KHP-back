<?php

namespace App\GraphQL\Builders;

use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Builder;

class StockMovementBuilder
{
    /**
     * Builder pour les mouvements de stock d'un ingrédient spécifique
     */
    public function forIngredient($root, array $args): Builder
    {
        // S'assurer que l'utilisateur ne peut voir que les mouvements de son entreprise
        $query = StockMovement::forCompany()
            ->where('trackable_type', 'App\\Models\\Ingredient');

        // Si l'ID de l'ingrédient est fourni dans les arguments
        if (isset($args['ingredientId'])) {
            $query->where('trackable_id', $args['ingredientId']);
        }

        // Filtrer par emplacement si spécifié
        if (isset($args['locationId'])) {
            $query->where('location_id', $args['locationId']);
        }

        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Builder pour les mouvements de stock d'une préparation spécifique
     */
    public function forPreparation($root, array $args): Builder
    {
        // S'assurer que l'utilisateur ne peut voir que les mouvements de son entreprise
        $query = StockMovement::forCompany()
            ->where('trackable_type', 'App\\Models\\Preparation');

        // Si l'ID de la préparation est fourni dans les arguments
        if (isset($args['preparationId'])) {
            $query->where('trackable_id', $args['preparationId']);
        }

        // Filtrer par emplacement si spécifié
        if (isset($args['locationId'])) {
            $query->where('location_id', $args['locationId']);
        }

        return $query->orderBy('created_at', 'desc');
    }
}
