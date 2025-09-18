<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING = 'PENDING';
    case SERVED = 'SERVED';
    case PAYED = 'PAYED';
    case CANCELED = 'CANCELED';

    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
