<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING = 'PENDING';
    case IN_PREP = 'IN_PREP';
    case READY = 'READY';
    case SERVED = 'SERVED';
    case PAYED = 'PAYED';
    case CANCELLED = 'CANCELLED';

    public static function values(): array
    {
        return array_map(fn(self $c) => $c->value, self::cases());
    }
}
