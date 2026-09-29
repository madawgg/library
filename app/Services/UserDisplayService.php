<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Presentation helpers for users shown in the interface.
 */
class UserDisplayService
{
    /**
     * Initials of the user's name, used in the avatar.
     */
    public function initials(User $user): string
    {
        return Str::of($user->name)
            ->explode(' ')
            ->map(fn (string $name) => Str::of($name)->substr(0, 1))
            ->implode('');
    }
}
