<?php

namespace App\Enums;

enum MenuServiceType: string
{
    case PREP = 'PREP';
    case DIRECT = 'DIRECT';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case) => $case->value, self::cases());
    }
}
