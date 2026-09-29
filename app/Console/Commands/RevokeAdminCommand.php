<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Services\UserRoleService;
use Illuminate\Console\Command;

class RevokeAdminCommand extends Command
{
    use ChangesUserRole;

    protected $signature = 'admin:revoke {email : Email del administrador que pasará a ser usuario}';

    protected $description = 'Degrada a un administrador al rol de usuario';

    public function handle(UserRoleService $roles): int
    {
        return $this->changeRole($roles, Role::User);
    }
}
