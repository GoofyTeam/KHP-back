<?php

namespace App\GraphQL\Resolvers;

use App\Enums\MeasurementUnit;
use App\Models\Ingredient;
use App\Models\Preparation;
use App\Models\PreparationEntity;
use App\Services\UnitConversionService;

class PreparationResolver
{
    public function __construct(private UnitConversionService $unitConversionService) {}

    /**
     * Return categories as a list wrapping the single category relation.
     * Keeps backward compatibility with a list-based GraphQL field.
     *
     * @return array<int, mixed>
     */
    public function categories(Preparation $preparation): array
    {
        $category = $preparation->category;

        return $category ? [$category] : [];
    }

    public function imageUrl(Preparation $preparation): ?string
    {
        if (! $preparation->image_url) {
            return null;
        }

        return url('/api/image-proxy/'.$preparation->image_url);
    }

    /**
     * Récupère les quantités par emplacement pour une préparation
     *
     * @param  Preparation  $preparation  La préparation concernée
     * @return array Tableau des quantités avec leurs emplacements associés
     */
    public function quantityByLocation(Preparation $preparation): array
    {
        $quantities = [];

        /** @var \App\Models\Location $location */
        foreach ($preparation->locations as $location) {
            /**
             * @var \Illuminate\Database\Eloquent\Relations\Pivot&object{quantity: float} $pivot
             */
            $pivot = $location->pivot;
            $quantities[] = [
                'quantity' => $pivot->quantity,
                'location' => $location,
            ];
        }

        return $quantities;
    }

    /**
     * Calcule la quantité maximale de préparation réalisable avec le stock actuel.
     *
     * @return array{quantity: float, unit: MeasurementUnit}
     */
    public function preparableQuantity(Preparation $preparation): array
    {
        $preparation->loadMissing('entities.entity');

        $max = INF;

        foreach ($preparation->entities as $component) {
            if (! $component instanceof PreparationEntity) {
                continue;
            }

            /** @var Ingredient|Preparation|null $entity */
            $entity = $component->entity;

            if (! $entity instanceof Ingredient && ! $entity instanceof Preparation) {
                $max = 0.0;
                break;
            }

            $locationEntity = $entity->locations()
                ->wherePivot('location_id', $component->location_id)
                ->first();

            /** @var (\Illuminate\Database\Eloquent\Relations\Pivot&object{quantity: float})|null $pivot */
            $pivot = $locationEntity?->pivot;
            $available = $pivot->quantity ?? 0.0;

            $required = $component->quantity ?? 0.0;
            if ($required <= 0) {
                continue;
            }

            if ($component->unit instanceof MeasurementUnit
                && $entity->unit instanceof MeasurementUnit
                && $component->unit !== $entity->unit) {
                $required = $this->unitConversionService->convert($required, $component->unit, $entity->unit);
            }

            if ($required <= 0) {
                continue;
            }

            $max = min($max, $available / $required);
        }

        if ($max === INF) {
            $max = 0.0;
        }

        return [
            'quantity' => max(0.0, (float) $max),
            'unit' => $preparation->unit,
        ];
    }
}
