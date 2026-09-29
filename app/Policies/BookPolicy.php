<?php

namespace App\Policies;

use App\Models\Book;
use App\Models\User;
use App\Services\BookService;
use App\Services\UserRoleService;

/**
 * A book can be managed by its owner and by administrators (spec 002, RF-01).
 * Creating books in a library is authorized with UserPolicy::manageLibrary.
 */
class BookPolicy
{
    public function __construct(private UserRoleService $roles, private BookService $books) {}

    public function view(User $actor, Book $book): bool
    {
        return $this->roles->canAccessLibraryOf($actor, $this->books->ownerOf($book));
    }

    public function update(User $actor, Book $book): bool
    {
        return $this->view($actor, $book);
    }

    public function delete(User $actor, Book $book): bool
    {
        return $this->view($actor, $book);
    }
}
