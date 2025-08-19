<?php

namespace App\GraphQL\Queries;

use App\Enums\MeasurementUnit;

class MeasurementUnitsQuery
{
    /**
     * Renvoie toutes les unités de mesure disponibles avec leurs informations
     */
    public function resolve(): array
    {
        $units = [];

        foreach (MeasurementUnit::cases() as $unit) {
            $units[] = [
                'value' => $unit->value,
                'label' => $unit->frenchLabel(),
                'category' => $unit->category(),
            ];
        }

        return $units;
    }
}
