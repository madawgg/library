<?php

namespace App\Enums;

enum Theme: string
{
    case Light = 'light';
    case Dark = 'dark';

    public function label(): string
    {
        return match ($this) {
            self::Light => 'Claro',
            self::Dark => 'Oscuro',
        };
    }

    /**
     * Class applied to the <html> element.
     */
    public function cssClass(): string
    {
        return $this === self::Dark ? 'dark' : '';
    }
}
