<?php

namespace App\Enums;

enum UnitEnum: string
{
    case GRAM = 'g';
    case KILOGRAM = 'kg';
    case MILLIGRAM = 'mg';
    case LITER = 'l';
    case MILLILITER = 'ml';
    case CENTILITER = 'cl';
    case TEASPOON = 'tsp';
    case TABLESPOON = 'tbsp';
    case CUP = 'cup';
    case PINCH = 'pinch';
    case PORTION = 'portion';
    case SLICE = 'slice';
    case PIECE = 'piece';
    case BOWL = 'bowl';
    case GLASS = 'glass';
    case DROP = 'drop';
    case SPRIG = 'sprig';
    case HANDFUL = 'handful';
    case CAN = 'can';
    case PACK = 'pack';
    case BOTTLE = 'bottle';
    case STICK = 'stick';
    case CLOVE = 'clove';
    case HEAD = 'head';
    case FILET = 'filet';
    case SHEET = 'sheet';
    case BAR = 'bar';
    case SACHET = 'sachet';
    case BOX = 'box';
    case TRAY = 'tray';
    case POT = 'pot';
    case BRANCH = 'branch';
    case PINCHES = 'pinches';
    case SPRAYS = 'sprays';
    case OUNCE = 'oz';
    case POUND = 'lb';
    case GALLON = 'gal';
    case QUART = 'qt';
    case PINT = 'pt';
    case FLUID_OUNCE = 'fl oz';
    case INCH = 'in';
    case CENTIMETER = 'cm';
    case MILLIMETER = 'mm';
    case SQUARE = 'square';
    case CUBE = 'cube';
    case BALL = 'ball';
    case PIN = 'pin';
    case ROLL = 'roll';
    case BUNCH = 'bunch';
    case DOZEN = 'dozen';
    case HALF = 'half';
    case QUARTER = 'quarter';
    case THIRD = 'third';
    case EIGHTH = 'eighth';
    case WHOLE = 'whole';
    case OTHER = 'other';

    public static function labels(): array
    {
        return [
            self::GRAM->value => 'Gramme',
            self::KILOGRAM->value => 'Kilogramme',
            self::MILLIGRAM->value => 'Milligramme',
            self::LITER->value => 'Litre',
            self::MILLILITER->value => 'Millilitre',
            self::CENTILITER->value => 'Centilitre',
            self::TEASPOON->value => 'Cuillère à café',
            self::TABLESPOON->value => 'Cuillère à soupe',
            self::CUP->value => 'Tasse',
            self::PINCH->value => 'Pincée',
            self::PORTION->value => 'Portion',
            self::SLICE->value => 'Tranche',
            self::PIECE->value => 'Pièce',
            self::BOWL->value => 'Bol',
            self::GLASS->value => 'Verre',
            self::DROP->value => 'Goutte',
            self::SPRIG->value => 'Brin',
            self::HANDFUL->value => 'Poignée',
            self::PACK->value => 'Paquet',
            self::BOTTLE->value => 'Bouteille',
            self::STICK->value => 'Bâton',
            self::CLOVE->value => 'Gousse',
            self::HEAD->value => 'Tête',
            self::FILET->value => 'Filet',
            self::SHEET->value => 'Feuille',
            self::BAR->value => 'Barre',
            self::SACHET->value => 'Sachet',
            self::BOX->value => 'Boîte',
            self::TRAY->value => 'Plateau',
            self::POT->value => 'Pot',
            self::BRANCH->value => 'Branche',
            self::PINCHES->value => 'Pincées',
            self::SPRAYS->value => 'Pulvérisations',
            self::OUNCE->value => 'Once',
            self::POUND->value => 'Livre',
            self::GALLON->value => 'Gallon',
            self::QUART->value => 'Quart',
            self::PINT->value => 'Pinte',
            self::FLUID_OUNCE->value => 'Once liquide',
            self::INCH->value => 'Pouce',
            self::CENTIMETER->value => 'Centimètre',
            self::MILLIMETER->value => 'Millimètre',
            self::SQUARE->value => 'Carré',
            self::CUBE->value => 'Cube',
            self::BALL->value => 'Boule',
            self::PIN->value => 'Épingle',
            self::ROLL->value => 'Rouleau',
            self::BUNCH->value => 'Bouquet',
            self::DOZEN->value => 'Douzaine',
            self::HALF->value => 'Demi',
            self::QUARTER->value => 'Quart',
            self::THIRD->value => 'Tiers',
            self::EIGHTH->value => 'Huitième',
            self::WHOLE->value => 'Entier',
            self::OTHER->value => 'Autre',
        ];
    }

    public function label(): string
    {
        return self::labels()[$this->value] ?? $this->value;
    }

    public static function values(): array
    {
        return array_map(fn ($case) => $case->value, self::cases());
    }

    public static function fromLabel(string $label): ?self
    {
        $reverseMap = [];

        foreach (self::labels() as $value => $mappedLabel) {
            $reverseMap[$mappedLabel][] = $value;
        }

        return isset($reverseMap[$label]) ? self::from($reverseMap[$label][0]) : null;
    }
}
