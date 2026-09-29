<?php

namespace App\Services;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Forgotten password flow: reset link and new password.
 */
class PasswordResetService
{
    /**
     * Send the reset link. The caller never learns whether the account exists.
     */
    public function sendResetLink(string $email): void
    {
        Password::sendResetLink(['email' => $email]);
    }

    /**
     * Reset the password with a valid token.
     *
     * @return string the password broker status (Password::PasswordReset on success)
     */
    public function reset(string $email, string $password, string $passwordConfirmation, string $token): string
    {
        return Password::reset(
            [
                'email' => $email,
                'password' => $password,
                'password_confirmation' => $passwordConfirmation,
                'token' => $token,
            ],
            function ($user) use ($password) {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );
    }

    public function succeeded(string $status): bool
    {
        return $status === Password::PasswordReset;
    }
}
