<?php

namespace App\Traits;

use App\Models\Ingredient;
use App\Models\Location;
use App\Models\Loss;
use App\Models\Preparation;
use App\Models\IngredientLocation;
use App\Models\LocationPreparation;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasLosses
{
    use HasStockMovements;

    /**
     * Historique des pertes associées à cette entité.
     */
    public function losses(): MorphMany
    {
        return $this->morphMany(Loss::class, 'lossable');
    }

    /**
     * Enregistrer une perte et mettre à jour le stock.
     */
    public function recordLoss(Location $location, float $quantity, string $reason): Loss
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Quantity must be positive');
        }

        if ($location->company_id !== $this->company_id) {
            throw new \InvalidArgumentException('Location does not belong to the same company');
        }

        $locationEntity = $this->locations()->where('locations.id', $location->id)->first();

        if (! $locationEntity) {
            throw new \RuntimeException('Trackable not stored at this location');
        }

        /** @var \Illuminate\Database\Eloquent\Relations\Pivot&object{quantity: float} $pivot */
        $pivot = $locationEntity->pivot;
        $available = (float) $pivot->quantity;

        if ($available < $quantity) {
            throw new \RuntimeException('Insufficient stock at location');
        }

        $newQuantity = round($available - $quantity, 2);

        // Mettre à jour la quantité à l'emplacement
        $pivotModel = $this instanceof Ingredient ? IngredientLocation::class : LocationPreparation::class;

        $pivotModel::withoutEvents(function () use ($location, $newQuantity) {
            $this->locations()->updateExistingPivot($location->id, [
                'quantity' => $newQuantity,
            ]);
        });

        $this->recordStockMovement($location, $available, $newQuantity, $reason);

        /** @var Loss $loss */
        $loss = $this->losses()->create([
            'location_id' => $location->id,
            'company_id' => $this->company_id,
            'user_id' => auth()->id(),
            'quantity' => $quantity,
            'reason' => $reason,
        ]);

        return $loss;
    }
}
