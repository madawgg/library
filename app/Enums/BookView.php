<?php

namespace App\Enums;

/**
 * Ways of showing the book listing (spec 003 RF-06, spec 004 RF-05).
 */
enum BookView: string
{
    case Grid = 'grid';
    case Table = 'table';
    case Shelf = 'shelf';

    public function label(): string
    {
        return match ($this) {
            self::Grid => 'Cuadrícula',
            self::Table => 'Tabla',
            self::Shelf => 'Estantería',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Grid => 'squares-2x2',
            self::Table => 'table-cells',
            self::Shelf => 'building-library',
        };
    }
}
