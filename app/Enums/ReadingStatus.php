<?php

namespace App\Enums;

enum ReadingStatus: string
{
    case Pending = 'pending';
    case Reading = 'reading';
    case Read = 'read';
    case Lent = 'lent';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Reading => 'Leyendo',
            self::Read => 'Leído',
            self::Lent => 'Prestado',
        };
    }
}
