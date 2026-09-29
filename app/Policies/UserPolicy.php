<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;
use App\Services\UserRoleService;

/**
 * Authorization for the user management panel and own-account actions (spec 001).
 */
class UserPolicy
{
    public function __construct(private UserRoleService $roles) {}

    public function viewAny(User $actor): bool
    {
        return $this->roles->hasAdminPrivileges($actor);
    }

    public function create(User $actor): bool
    {
        return $this->roles->hasAdminPrivileges($actor);
    }

    public function update(User $actor, User $target): bool
    {
        return $this->roles->canManage($actor, $target);
    }

    public function delete(User $actor, User $target): bool
    {
        return $this->roles->canManage($actor, $target);
    }

    public function assignRole(User $actor, Role $role): bool
    {
        return $this->roles->canAssignRole($actor, $role);
    }

    /**
     * Manage the library (rooms, bookcases, books) of the given owner.
     */
    public function manageLibrary(User $actor, User $owner): bool
    {
        return $this->roles->canAccessLibraryOf($actor, $owner);
    }

    public function deleteOwnAccount(User $actor, User $target): bool
    {
        return $actor->is($target) && $this->roles->canDeleteOwnAccount($actor);
    }
}
