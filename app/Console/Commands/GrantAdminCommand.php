<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Services\UserRoleService;
use Illuminate\Console\Command;

class GrantAdminCommand extends Command
{
    use ChangesUserRole;

    protected $signature = 'admin:grant {email : Email de la cuenta que pasará a ser administrador}';

    protected $description = 'Promueve una cuenta al rol de administrador';

    public function handle(UserRoleService $roles): int
    {
        return $this->changeRole($roles, Role::Admin);
    }
}
