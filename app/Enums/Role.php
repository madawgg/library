<?php

namespace App\Enums;

enum Role: string
{
    case User = 'user';
    case Admin = 'admin';
    case SuperAdmin = 'super_admin';

    /**
     * Human readable name shown in the interface.
     */
    public function label(): string
    {
        return match ($this) {
            self::User => 'Usuario',
            self::Admin => 'Administrador',
            self::SuperAdmin => 'Super administrador',
        };
    }

    /**
     * Roles that can be assigned from the interface or the artisan commands.
     *
     * @return list<self>
     */
    public static function assignable(): array
    {
        return [self::User, self::Admin];
    }
}
