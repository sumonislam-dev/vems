<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs out a user whose account was deactivated or suspended while they
 * were logged in (or who is remembered by a "Remember me" cookie), so
 * deactivating an account takes effect on their next click. LoginRequest
 * refuses such accounts at the login form.
 */
class EnsureUserIsActive
{
    public const MESSAGE = 'Your account is inactive. Please contact your administrator.';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->status !== 'active') {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['login' => self::MESSAGE]);
        }

        return $next($request);
    }
}
