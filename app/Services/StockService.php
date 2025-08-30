<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Location;
use App\Models\Preparation;
use Illuminate\Support\Facades\DB;

class StockService
{
    public function __construct(private PerishableService $perishableService) {}

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

    public function move(Ingredient|Preparation $model, int $fromLocationId, int $toLocationId, int $companyId, float $quantity): void
    {
        DB::transaction(function () use ($model, $fromLocationId, $toLocationId, $companyId, $quantity) {
            $this->remove($model, $fromLocationId, $companyId, $quantity);
            $this->add($model, $toLocationId, $companyId, $quantity);

            if ($model instanceof Ingredient) {
                $this->perishableService->remove($model->id, $fromLocationId, $companyId, $quantity);
                $this->perishableService->add($model->id, $toLocationId, $companyId, $quantity);
            }
        });
    }
}
