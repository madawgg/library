<?php

namespace Database\Seeders;

use App\Services\UserRoleService;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    /**
     * Create the single super administrator from the credentials in the .env file.
     */
    public function run(UserRoleService $roles): void
    {
        $roles->ensureSuperAdminExists(
            config('auth.super_admin.name'),
            config('auth.super_admin.email'),
            config('auth.super_admin.password'),
        );
    }
}
