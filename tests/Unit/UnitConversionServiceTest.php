<?php

namespace Tests\Unit;

use App\Enums\MeasurementUnit;
use App\Services\UnitConversionService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UnitConversionServiceTest extends TestCase
{
    private const DELTA = 0.0001;

    private UnitConversionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new UnitConversionService;
    }

    #[DataProvider('massUnits')]
    public function test_converts_mass_units_with_gram_reference(MeasurementUnit $unit, float $grams): void
    {
        $this->assertEqualsWithDelta($grams, $this->service->convert(1, $unit, MeasurementUnit::GRAM), self::DELTA);
        $this->assertEqualsWithDelta(1, $this->service->convert($grams, MeasurementUnit::GRAM, $unit), self::DELTA);
    }

    public static function massUnits(): array
    {
        return [
            'kg' => [MeasurementUnit::KILOGRAM, 1000.0],
            'hg' => [MeasurementUnit::HECTOGRAM, 100.0],
            'dag' => [MeasurementUnit::DECAGRAM, 10.0],
            'g' => [MeasurementUnit::GRAM, 1.0],
            'dg' => [MeasurementUnit::DECIGRAM, 0.1],
            'cg' => [MeasurementUnit::CENTIGRAM, 0.01],
            'mg' => [MeasurementUnit::MILLIGRAM, 0.001],
        ];
    }

    #[DataProvider('volumeUnits')]
    public function test_converts_volume_units_with_litre_reference(MeasurementUnit $unit, float $litres): void
    {
        $this->assertEqualsWithDelta($litres, $this->service->convert(1, $unit, MeasurementUnit::LITRE), self::DELTA);
        $this->assertEqualsWithDelta(1, $this->service->convert($litres, MeasurementUnit::LITRE, $unit), self::DELTA);
    }

    public static function volumeUnits(): array
    {
        return [
            'kL' => [MeasurementUnit::KILOLITRE, 1000.0],
            'hL' => [MeasurementUnit::HECTOLITRE, 100.0],
            'daL' => [MeasurementUnit::DECALITRE, 10.0],
            'L' => [MeasurementUnit::LITRE, 1.0],
            'dL' => [MeasurementUnit::DECILITRE, 0.1],
            'cL' => [MeasurementUnit::CENTILITRE, 0.01],
            'mL' => [MeasurementUnit::MILLILITRE, 0.001],
        ];
    }

    public function test_returns_same_quantity_for_identity_conversion(): void
    {
        $this->assertSame(5.0, $this->service->convert(5, MeasurementUnit::UNIT, MeasurementUnit::UNIT));
    }
}
