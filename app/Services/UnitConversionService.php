<?php

namespace App\Services;

use App\Enums\MeasurementUnit;

class UnitConversionService
{
    private const MASS_FACTORS = [
        'kg' => 1000.0,
        'hg' => 100.0,
        'dag' => 10.0,
        'g' => 1.0,
        'dg' => 0.1,
        'cg' => 0.01,
        'mg' => 0.001,
    ];

    private const VOLUME_FACTORS = [
        'kL' => 1000.0,
        'hL' => 100.0,
        'daL' => 10.0,
        'L' => 1.0,
        'dL' => 0.1,
        'cL' => 0.01,
        'mL' => 0.001,
    ];

    private const UNIT_FACTORS = [
        'unit' => 1.0,
    ];

    public function convert(float $quantity, MeasurementUnit $from, MeasurementUnit $to): float
    {
        if ($from === $to) {
            return $quantity;
        }

        $fromFactor = $this->factor($from);
        $toFactor = $this->factor($to);

        // convert to base unit then to target
        return $quantity * $fromFactor / $toFactor;
    }

    private function factor(MeasurementUnit $unit): float
    {
        $value = $unit->value;
        if (isset(self::MASS_FACTORS[$value])) {
            return self::MASS_FACTORS[$value];
        }
        if (isset(self::VOLUME_FACTORS[$value])) {
            return self::VOLUME_FACTORS[$value];
        }
        if (isset(self::UNIT_FACTORS[$value])) {
            return self::UNIT_FACTORS[$value];
        }

        throw new \InvalidArgumentException("Unknown measurement unit: {$value}");
    }
}
