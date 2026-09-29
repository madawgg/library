<?php

namespace App\Policies;

use App\Models\Bookcase;
use App\Models\User;
use App\Services\BookcaseService;
use App\Services\UserRoleService;

/**
 * Bookcases belong to a library: its owner and administrators can manage them (spec 004, RF-01).
 */
class BookcasePolicy
{
    public function __construct(private UserRoleService $roles, private BookcaseService $bookcases) {}

    public function update(User $actor, Bookcase $bookcase): bool
    {
        return $this->roles->canAccessLibraryOf($actor, $this->bookcases->ownerOf($bookcase));
    }

    public function delete(User $actor, Bookcase $bookcase): bool
    {
        return $this->update($actor, $bookcase);
    }
}
