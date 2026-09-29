<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Exceptions\SuperAdminProtectedException;
use App\Services\UserRoleService;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Shared handling for the admin:grant and admin:revoke commands.
 */
trait ChangesUserRole
{
    protected function changeRole(UserRoleService $roles, Role $role): int
    {
        $email = (string) $this->argument('email');

        try {
            $user = $roles->changeRoleByEmail($email, $role);
        } catch (ModelNotFoundException) {
            $this->error("No existe ningún usuario con el email {$email}.");

            return self::FAILURE;
        } catch (SuperAdminProtectedException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("{$user->email} ahora tiene el rol {$role->label()}.");

        return self::SUCCESS;
    }
}
