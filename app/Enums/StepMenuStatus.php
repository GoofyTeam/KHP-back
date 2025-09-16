<?php

namespace App\Enums;

enum StepMenuStatus: string
{
    case PENDING = 'PENDING';
    case IN_PREP = 'IN_PREP';
    case READY = 'READY';
    case SERVED = 'SERVED';

    public static function values(): array
    {
        return array_map(static fn (self $case) => $case->value, self::cases());
    }
}
