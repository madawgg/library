<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Events\Verified;

/**
 * Email verification (prepared but not enforced, spec 001 RF-04).
 */
class EmailVerificationService
{
    public function isVerified(User $user): bool
    {
        return $user->hasVerifiedEmail();
    }

    /**
     * Send the verification link unless the email is already verified.
     *
     * @return bool whether a link was sent
     */
    public function sendVerificationLink(User $user): bool
    {
        if ($user->hasVerifiedEmail()) {
            return false;
        }

        $user->sendEmailVerificationNotification();

        return true;
    }

    public function markAsVerified(User $user): void
    {
        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }
    }
}
