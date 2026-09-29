<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Login, logout and password confirmation for the web guard.
 */
class AuthenticationService
{
    private const MAX_LOGIN_ATTEMPTS = 5;

    /**
     * Attempt to log in, with rate limiting per email and IP address.
     *
     * @throws ValidationException when the credentials are wrong or there are too many attempts
     */
    public function login(string $email, string $password, bool $remember, string $ipAddress): void
    {
        $throttleKey = Str::transliterate(Str::lower($email).'|'.$ipAddress);

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_LOGIN_ATTEMPTS)) {
            event(new Lockout(request()));

            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => __('auth.throttle', ['seconds' => $seconds, 'minutes' => ceil($seconds / 60)]),
            ]);
        }

        if (! Auth::attempt(['email' => $email, 'password' => $password], $remember)) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        RateLimiter::clear($throttleKey);
        Session::regenerate();
    }

    public function logout(): void
    {
        Auth::guard('web')->logout();

        Session::invalidate();
        Session::regenerateToken();
    }

    /**
     * Confirm the user's password before a sensitive action.
     *
     * @throws ValidationException when the password is wrong
     */
    public function confirmPassword(User $user, string $password): void
    {
        if (! Auth::guard('web')->validate(['email' => $user->email, 'password' => $password])) {
            throw ValidationException::withMessages(['password' => __('auth.password')]);
        }

        session(['auth.password_confirmed_at' => time()]);
    }
}
