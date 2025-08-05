<?php

namespace App\Traits;

use App\Models\Location;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasStockMovements
{
    /**
     * Obtenir les mouvements de stock associés à cette entité
     */
    public function stockMovements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'trackable');
    }

    /**
     * Enregistrer un mouvement de stock dans l'historique
     * SANS modifier les quantités (les contrôleurs s'en chargent)
     *
     * @param  Location  $location  L'emplacement concerné
     * @param  float  $quantityBefore  Quantité avant le mouvement
     * @param  float  $quantityAfter  Quantité après le mouvement
     * @return StockMovement|null Le mouvement créé ou null si pas de différence significative
     */
    public function recordStockMovement(Location $location, float $quantityBefore, float $quantityAfter): ?StockMovement
    {

        // Validation: Les quantités ne doivent pas être négatives
        if ($quantityBefore < 0 || $quantityAfter < 0) {
            return null;
        }
        // Validation: L'emplacement doit appartenir à la même société que l'entité trackable
        if ($location->company_id !== $this->company_id) {
            return null;
        }
        // S'assurer que les quantités sont des nombres
        $quantityBefore = (float) $quantityBefore;
        $quantityAfter = (float) $quantityAfter;

        // Arrondir à 2 décimales
        $quantityBefore = round($quantityBefore, 2);
        $quantityAfter = round($quantityAfter, 2);

        $difference = $quantityAfter - $quantityBefore;

        // Ne pas créer de mouvement si la différence est nulle ou trop petite
        if (abs($difference) < 0.01) { // Utiliser 0.01 comme seuil minimal, car nous avons 2 décimales
            return null;
        }

        $type = $difference > 0 ? 'addition' : 'withdrawal';

        /** @var StockMovement */
        $stockMovement = $this->stockMovements()->create([
            'location_id' => $location->id,
            'company_id' => $this->company_id,
            'user_id' => auth()->id(),
            'type' => $type,
            'quantity' => round(abs($difference), 2),
            'quantity_before' => $quantityBefore,
            'quantity_after' => $quantityAfter,
        ]);

        return $stockMovement;
    }
}
