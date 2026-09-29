<?php

namespace App\Services;

use App\Enums\Role;
use App\Exceptions\SuperAdminProtectedException;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use RuntimeException;

/**
 * Role rules for users, administrators and the super administrator (spec 001).
 */
class UserRoleService
{
    public function isSuperAdmin(User $user): bool
    {
        return $user->role === Role::SuperAdmin;
    }

    /**
     * Administrators and the super administrator can access any user's resources.
     */
    public function hasAdminPrivileges(User $user): bool
    {
        return in_array($user->role, [Role::Admin, Role::SuperAdmin], true);
    }

    /**
     * A user can access their own library; administrators can access anyone's (spec 001, RF-09).
     */
    public function canAccessLibraryOf(User $actor, User $owner): bool
    {
        return $actor->is($owner) || $this->hasAdminPrivileges($actor);
    }

    /**
     * Whether the actor can manage (edit or delete) the target account from the admin panel.
     */
    public function canManage(User $actor, User $target): bool
    {
        return match ($actor->role) {
            Role::SuperAdmin => $target->role !== Role::SuperAdmin,
            Role::Admin => $target->role === Role::User,
            default => false,
        };
    }

    /**
     * Whether the actor can give the given role to an account (when creating or editing it).
     */
    public function canAssignRole(User $actor, Role $role): bool
    {
        if ($role === Role::SuperAdmin) {
            return false;
        }

        return $role === Role::User
            ? $this->hasAdminPrivileges($actor)
            : $this->isSuperAdmin($actor);
    }

    public function canDeleteOwnAccount(User $user): bool
    {
        return ! $this->isSuperAdmin($user);
    }

    /**
     * Change the role of an account between user and administrator.
     */
    public function changeRole(User $target, Role $role): void
    {
        if ($this->isSuperAdmin($target)) {
            throw new SuperAdminProtectedException;
        }

        if (! in_array($role, Role::assignable(), true)) {
            throw new SuperAdminProtectedException('No se puede asignar el rol de super administrador.');
        }

        $target->role = $role;
        $target->save();
    }

    /**
     * Change the role of the account with the given email (used by the artisan commands).
     *
     * @throws ModelNotFoundException when no account has that email
     * @throws SuperAdminProtectedException when the account is the super administrator
     */
    public function changeRoleByEmail(string $email, Role $role): User
    {
        $user = User::where('email', $email)->firstOrFail();

        $this->changeRole($user, $role);

        return $user;
    }

    /**
     * Create the super administrator unless one already exists.
     */
    public function ensureSuperAdminExists(?string $name, ?string $email, ?string $password): User
    {
        $existing = User::where('role', Role::SuperAdmin)->first();

        if ($existing) {
            return $existing;
        }

        if (blank($email) || blank($password)) {
            throw new RuntimeException('Faltan SUPER_ADMIN_EMAIL o SUPER_ADMIN_PASSWORD en el .env para crear el super administrador.');
        }

        $superAdmin = User::firstOrNew(['email' => $email]);
        $superAdmin->name = $name ?: 'Super administrador';
        $superAdmin->password = $password;
        $superAdmin->role = Role::SuperAdmin;
        $superAdmin->email_verified_at ??= now();
        $superAdmin->save();

        return $superAdmin;
    }
}
