<?php

namespace App\Enums;

enum BookCondition: string
{
    case New = 'new';
    case VeryGood = 'very_good';
    case Good = 'good';
    case Acceptable = 'acceptable';
    case Damaged = 'damaged';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Nuevo',
            self::VeryGood => 'Muy bueno',
            self::Good => 'Bueno',
            self::Acceptable => 'Aceptable',
            self::Damaged => 'Deteriorado',
        };
    }
}
