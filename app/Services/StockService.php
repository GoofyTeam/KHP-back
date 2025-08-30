<?php

namespace App\Services;

use App\Models\Location;
use Illuminate\Database\Eloquent\Model;

class StockService
{
    public function add(Model $model, int $locationId, int $companyId, float $quantity): float
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

    public function remove(Model $model, int $locationId, int $companyId, float $quantity): float
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
