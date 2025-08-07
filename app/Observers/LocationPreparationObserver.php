<?php

namespace App\Observers;

use App\Models\Location;
use App\Models\LocationPreparation;
use App\Models\Preparation;

class LocationPreparationObserver
{
    /**
     * Gérer l'événement "created".
     */
    public function created(LocationPreparation $pivot): void
    {
        $preparation = Preparation::find($pivot->preparation_id);
        $location = Location::find($pivot->location_id);

        // Enregistrer un mouvement d'ajout (de 0 à la quantité actuelle)
        $preparation->recordStockMovement($location, 0, $pivot->quantity);
    }

    /**
     * Gérer l'événement "updated".
     */
    public function updated(LocationPreparation $pivot): void
    {
        // Récupérer les valeurs avant et après la mise à jour
        if ($pivot->isDirty('quantity')) {
            $preparation = Preparation::find($pivot->preparation_id);
            $location = Location::find($pivot->location_id);

            $oldQuantity = $pivot->getOriginal('quantity');
            $newQuantity = $pivot->quantity;

            // Enregistrer le mouvement
            $preparation->recordStockMovement($location, $oldQuantity, $newQuantity);
        }
    }

    /**
     * Gérer l'événement "deleted".
     */
    public function deleted(LocationPreparation $pivot): void
    {
        $preparation = Preparation::find($pivot->preparation_id);
        $location = Location::find($pivot->location_id);

        // Enregistrer un mouvement de retrait (de la quantité actuelle à 0)
        $preparation->recordStockMovement($location, $pivot->quantity, 0);
    }
}
