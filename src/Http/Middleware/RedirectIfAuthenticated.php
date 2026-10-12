<?php

namespace AdamDziuk\PhoneLogin\Http\Middleware;

use AdamDziuk\PhoneLogin\PhoneLogin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The guest check for the package's own routes. Laravel's guest middleware
 * sends a signed-in user wherever the app sends all of them — redirectUsersTo,
 * else the "dashboard" route — which next to staff accounts is the staff area.
 */
class RedirectIfAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (PhoneLogin::auth()->check()) {
            return redirect((string) config('phone-login.home'));
        }

        return $next($request);
    }
}
