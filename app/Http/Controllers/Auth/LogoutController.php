<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuthenticationService;
use Illuminate\Http\RedirectResponse;

class LogoutController extends Controller
{
    /**
     * Log the current user out of the application.
     */
    public function __invoke(AuthenticationService $authentication): RedirectResponse
    {
        $authentication->logout();

        return redirect('/');
    }
}
