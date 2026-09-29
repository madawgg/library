<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when an operation would modify, demote or delete the super administrator.
 */
class SuperAdminProtectedException extends RuntimeException
{
    public function __construct(string $message = 'El super administrador no se puede modificar ni eliminar.')
    {
        parent::__construct($message);
    }
}
