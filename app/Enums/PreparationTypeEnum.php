<?php

namespace App\Enums;

enum PreparationTypeEnum: string
{
    case SIMPLE = 'simple';
    case COMPOSITE = 'composite';

    public static function values(): array
    {
        return array_map(fn ($case) => $case->value, self::cases());
    }
}
