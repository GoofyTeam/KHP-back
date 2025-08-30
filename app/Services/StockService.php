<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Location;
use App\Models\Preparation;

class StockService
{
    public function add(Ingredient|Preparation $model, int $locationId, int $companyId, float $quantity): float
    {
        $location = Location::where('id', $locationId)
            ->where('company_id', $companyId)
            ->firstOrFail();

        $current = (float) ($model->locations()->find($location->id)?->pivot->quantity ?? 0);
        $newQuantity = $current + $quantity;

        $model->locations()->syncWithoutDetaching([
            $location->id => ['quantity' => $newQuantity],
        ]);

        return $newQuantity;
    }

    public function remove(Ingredient|Preparation $model, int $locationId, int $companyId, float $quantity): float
    {
        $location = Location::where('id', $locationId)
            ->where('company_id', $companyId)
            ->firstOrFail();

        $current = (float) ($model->locations()->find($location->id)?->pivot->quantity ?? 0);
        $newQuantity = $current - $quantity;

        if ($newQuantity < 0) {
            throw new \InvalidArgumentException('Quantity cannot be negative');
        }

        $model->locations()->syncWithoutDetaching([
            $location->id => ['quantity' => $newQuantity],
        ]);

        return $newQuantity;
    }
}

