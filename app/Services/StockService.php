<?php

namespace App\Services;

use App\Enums\MeasurementUnit;
use App\Models\Ingredient;
use App\Models\Location;
use App\Models\Preparation;
use Illuminate\Support\Facades\DB;

class StockService
{
    public const DEFAULT_ADD_REASON = 'Manual Addition';

    public const DEFAULT_REMOVE_REASON = 'Manual Withdrawal';

    public function __construct(
        private PerishableService $perishableService,
        private UnitConversionService $unitConversionService
    ) {}

    public function add(
        Ingredient|Preparation $model,
        int $locationId,
        int $companyId,
        float $quantity,
        ?string $reason = null,
        ?MeasurementUnit $unit = null
    ): float {
        $unit ??= $model->unit;
        if ($unit !== $model->unit) {
            $quantity = $this->unitConversionService->convert($quantity, $unit, $model->unit);
        }

        return DB::transaction(function () use ($model, $locationId, $companyId, $quantity, $reason) {
            $location = Location::where('id', $locationId)
                ->where('company_id', $companyId)
                ->firstOrFail();

            $pivotQuery = $model->locations()->newPivotStatementForId($location->id);
            $pivot = $pivotQuery->lockForUpdate()->first();
            $current = (float) ($pivot->quantity ?? 0);

            if ($pivot) {
                $pivotQuery->increment('quantity', $quantity);
            } else {
                $model->locations()->attach($location->id, ['quantity' => $quantity]);
            }

            $newQuantity = $current + $quantity;
            $model->recordStockMovement($location, $current, $newQuantity, $reason ?? self::DEFAULT_ADD_REASON);

            if ($model instanceof Ingredient) {
                $this->perishableService->add($model->id, $locationId, $companyId, $quantity);
            }

            return $newQuantity;
        });
    }

    public function remove(
        Ingredient|Preparation $model,
        int $locationId,
        int $companyId,
        float $quantity,
        ?string $reason = null,
        ?MeasurementUnit $unit = null
    ): float {
        $unit ??= $model->unit;
        if ($unit !== $model->unit) {
            $quantity = $this->unitConversionService->convert($quantity, $unit, $model->unit);
        }

        return DB::transaction(function () use ($model, $locationId, $companyId, $quantity, $reason) {
            $location = Location::where('id', $locationId)
                ->where('company_id', $companyId)
                ->firstOrFail();

            $pivotQuery = $model->locations()->newPivotStatementForId($location->id);
            $pivot = $pivotQuery->lockForUpdate()->first();
            $current = (float) ($pivot->quantity ?? 0);
            $newQuantity = $current - $quantity;

            if ($newQuantity < 0) {
                throw new \InvalidArgumentException('Quantity cannot be negative');
            }

            if ($pivot) {
                $pivotQuery->decrement('quantity', $quantity);
            }

            $model->recordStockMovement($location, $current, $newQuantity, $reason ?? self::DEFAULT_REMOVE_REASON);

            if ($model instanceof Ingredient) {
                $this->perishableService->remove($model->id, $locationId, $companyId, $quantity);
            }

            return $newQuantity;
        });
    }

    public function move(
        Ingredient|Preparation $model,
        int $fromLocationId,
        int $toLocationId,
        int $companyId,
        float $quantity,
        ?MeasurementUnit $unit = null
    ): void {
        $unit ??= $model->unit;
        if ($unit !== $model->unit) {
            $quantity = $this->unitConversionService->convert($quantity, $unit, $model->unit);
        }

        DB::transaction(function () use ($model, $fromLocationId, $toLocationId, $companyId, $quantity) {
            $from = Location::where('id', $fromLocationId)
                ->where('company_id', $companyId)
                ->firstOrFail();
            $to = Location::where('id', $toLocationId)
                ->where('company_id', $companyId)
                ->firstOrFail();

            $reason = "Moved from {$from->name} to {$to->name}";

            $fromPivotQuery = $model->locations()->newPivotStatementForId($from->id);
            $fromPivot = $fromPivotQuery->lockForUpdate()->first();
            $fromCurrent = (float) ($fromPivot->quantity ?? 0);
            $fromNew = $fromCurrent - $quantity;

            if ($fromNew < 0) {
                throw new \InvalidArgumentException('Quantity cannot be negative');
            }

            if ($fromPivot) {
                $fromPivotQuery->decrement('quantity', $quantity);
            }

            $model->recordStockMovement($from, $fromCurrent, $fromNew, $reason, 'movement');

            $toPivotQuery = $model->locations()->newPivotStatementForId($to->id);
            $toPivot = $toPivotQuery->lockForUpdate()->first();
            $toCurrent = (float) ($toPivot->quantity ?? 0);

            if ($toPivot) {
                $toPivotQuery->increment('quantity', $quantity);
            } else {
                $model->locations()->attach($to->id, ['quantity' => $quantity]);
            }

            $toNew = $toCurrent + $quantity;
            $model->recordStockMovement($to, $toCurrent, $toNew, $reason, 'movement');

            if ($model instanceof Ingredient) {
                $this->perishableService->remove($model->id, $fromLocationId, $companyId, $quantity);
                $this->perishableService->add($model->id, $toLocationId, $companyId, $quantity);
            }
        });
    }
}
