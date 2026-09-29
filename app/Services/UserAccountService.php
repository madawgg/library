<?php

namespace App\Services;

use App\Enums\Role;
use App\Exceptions\SuperAdminProtectedException;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\Eloquent\Collection;

/**
 * Account lifecycle: registration, profile changes and deletion (spec 001).
 */
class UserAccountService
{
    public function __construct(private UserRoleService $roles) {}

    /**
     * Public registration: the account always gets the user role.
     */
    public function register(string $name, string $email, string $password): User
    {
        $user = $this->createAccount($name, $email, $password, Role::User);

        event(new Registered($user));

        return $user;
    }

    public function createAccount(string $name, string $email, string $password, Role $role): User
    {
        if (! in_array($role, Role::assignable(), true)) {
            throw new SuperAdminProtectedException('No se puede crear otro super administrador.');
        }

        $user = new User(['name' => $name, 'email' => $email, 'password' => $password]);
        $user->role = $role;
        $user->save();

        return $user;
    }

    /**
     * All accounts for the management panel, ordered by name.
     *
     * @return Collection<int, User>
     */
    public function listAccounts(): Collection
    {
        return User::orderBy('name')->get();
    }

    /**
     * Update name and email. Changing the email marks it as unverified.
     */
    public function updateProfile(User $user, string $name, string $email): User
    {
        $user->fill(['name' => $name, 'email' => $email]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return $user;
    }

    public function updatePassword(User $user, string $password): void
    {
        $user->update(['password' => $password]);
    }

    /**
     * Delete an account. The super administrator can never be deleted.
     */
    public function deleteAccount(User $user): void
    {
        if ($this->roles->isSuperAdmin($user)) {
            throw new SuperAdminProtectedException;
        }

        $user->delete();
    }
}
