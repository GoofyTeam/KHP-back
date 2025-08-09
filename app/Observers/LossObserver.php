<?php

namespace App\Observers;

use App\Models\Location;
use App\Models\Loss;

class LossObserver
{
    /**
     * Gérer l'événement "created".
     */
    public function created(Loss $loss): void
    {
        $ingredient = $loss->ingredient; // Peut être un Ingredient ou une Preparation
        $location = Location::find($loss->location_id);

        if (! $ingredient || ! $location) {
            return;
        }

        // Récupérer la quantité actuelle dans la localisation
        $currentQuantity = $ingredient
            ->locations()
            ->where('locations.id', $location->id)
            ->first()
            ?->pivot
            ?->quantity ?? 0;

        $newQuantity = max($currentQuantity - $loss->quantity, 0);

        // Mise à jour du pivot
        $ingredient
            ->locations()
            ->updateExistingPivot($location->id, [
                'quantity' => $newQuantity,
            ]);

        // Enregistrer le mouvement de stock
        $ingredient->recordStockMovement($location, $currentQuantity, $newQuantity);
    }
}
