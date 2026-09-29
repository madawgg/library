<?php

namespace App\Policies;

use App\Models\Loan;
use App\Models\User;
use App\Services\UserRoleService;

/**
 * The loan history can be seen with the book (BookPolicy::view), but only administrators
 * can correct or delete its entries (spec 002, RF-05b).
 */
class LoanPolicy
{
    public function __construct(private UserRoleService $roles) {}

    public function update(User $actor, Loan $loan): bool
    {
        return $this->roles->hasAdminPrivileges($actor);
    }

    public function delete(User $actor, Loan $loan): bool
    {
        return $this->roles->hasAdminPrivileges($actor);
    }
}
