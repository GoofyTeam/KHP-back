<?php

namespace App\Observers;

use App\Models\Ingredient;
use App\Models\IngredientLocation;
use App\Models\Location;

class IngredientLocationObserver
{
    /**
     * Gérer l'événement "created".
     */
    public function created(IngredientLocation $pivot): void
    {
        $ingredient = Ingredient::find($pivot->ingredient_id);
        $location = Location::find($pivot->location_id);

        // Enregistrer un mouvement d'ajout (de 0 à la quantité actuelle)
        $ingredient->recordStockMovement($location, 0, $pivot->quantity);
    }

    /**
     * Gérer l'événement "updated".
     */
    public function updated(IngredientLocation $pivot): void
    {
        // Récupérer les valeurs avant et après la mise à jour
        if ($pivot->isDirty('quantity')) {
            $ingredient = Ingredient::find($pivot->ingredient_id);
            $location = Location::find($pivot->location_id);

            $oldQuantity = $pivot->getOriginal('quantity');
            $newQuantity = $pivot->quantity;

            // Enregistrer le mouvement
            $ingredient->recordStockMovement($location, $oldQuantity, $newQuantity);
        }
    }

    /**
     * Gérer l'événement "deleted".
     */
    public function deleted(IngredientLocation $pivot): void
    {
        $ingredient = Ingredient::find($pivot->ingredient_id);
        $location = Location::find($pivot->location_id);

        // Enregistrer un mouvement de retrait (de la quantité actuelle à 0)
        $ingredient->recordStockMovement($location, $pivot->quantity, 0);
    }
}
