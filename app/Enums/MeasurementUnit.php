<?php

namespace App\Enums;

enum MeasurementUnit: string
{
    // Mass (gram variants)
    case KILOGRAM = 'kg';
    case HECTOGRAM = 'hg';
    case DECAGRAM = 'dag';
    case GRAM = 'g';
    case DECIGRAM = 'dg';
    case CENTIGRAM = 'cg';
    case MILLIGRAM = 'mg';

    // Volume (litre variants)
    case KILOLITRE = 'kL';
    case HECTOLITRE = 'hL';
    case DECALITRE = 'daL';
    case LITRE = 'L';
    case DECILITRE = 'dL';
    case CENTILITRE = 'cL';
    case MILLILITRE = 'mL';

    // Unitary (single unit only)
    case UNIT = 'unit';

    public function frenchLabel(): string
    {
        return match ($this) {
            // Mass
            self::KILOGRAM => 'Kilogramme (kg)',
            self::HECTOGRAM => 'Hectogramme (hg)',
            self::DECAGRAM => 'Décagramme (dag)',
            self::GRAM => 'Gramme (g)',
            self::DECIGRAM => 'Décigramme (dg)',
            self::CENTIGRAM => 'Centigramme (cg)',
            self::MILLIGRAM => 'Milligramme (mg)',

            // Volume
            self::KILOLITRE => 'Kilolitre (kL)',
            self::HECTOLITRE => 'Hectolitre (hL)',
            self::DECALITRE => 'Décilitre (daL)',
            self::LITRE => 'Litre (L)',
            self::DECILITRE => 'Décilitre (dL)',
            self::CENTILITRE => 'Centilitre (cL)',
            self::MILLILITRE => 'Millilitre (mL)',

            // Unit
            self::UNIT => 'Unité',
        };
    }

    public function category(): string
    {
        return match ($this) {
            self::KILOGRAM, self::HECTOGRAM, self::DECAGRAM, self::GRAM, self::DECIGRAM, self::CENTIGRAM, self::MILLIGRAM => 'masse',
            self::KILOLITRE, self::HECTOLITRE, self::DECALITRE, self::LITRE, self::DECILITRE, self::CENTILITRE, self::MILLILITRE => 'volume',
            default => 'unité',
        };
    }

    public static function baseUnits(): array
    {
        return [
            'mass' => self::GRAM,
            'volume' => self::LITRE,
            'unit' => self::UNIT,
        ];
    }

    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }

    public static function fromOpenFoodFacts(?string $unit): self
    {
        if (empty($unit)) {
            return self::UNIT;
        }

        // Normalisation : OpenFoodFacts peut envoyer des majuscules/minuscules
        $normalized = strtolower(trim($unit));

        // On vérifie si ça correspond directement à un case de l'enum
        foreach (self::cases() as $case) {
            if (strtolower($case->value) === $normalized) {
                return $case;
            }
        }

        // Sinon fallback sur "unit"
        return self::UNIT;
    }
}
