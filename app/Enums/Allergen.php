<?php

namespace App\Enums;

enum Allergen: string
{
    case GLUTEN = 'gluten';
    case TREE_NUTS = 'fruits_a_coque';
    case CRUSTACEANS = 'crustaces';
    case CELERY = 'celeri';
    case EGGS = 'oeufs';
    case MUSTARD = 'moutarde';
    case FISH = 'poisson';
    case SOY = 'soja';
    case MILK = 'lait';
    case SULPHITES = 'sulfites';
    case SESAME = 'sesame';
    case LUPIN = 'lupin';
    case PEANUTS = 'arachides';
    case MOLLUSCS = 'mollusques';

    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
