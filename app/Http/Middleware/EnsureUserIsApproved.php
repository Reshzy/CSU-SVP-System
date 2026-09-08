<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps unapproved accounts out of the authenticated app.
 *
 * `Fortify::authenticateUsing()` already blocks them at the login form, but
 * passkey sign-in and any session that outlives a CEO deactivation never pass
 * through that pipeline. This is the enforcement point.
 */
class EnsureUserIsApproved
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isApproved()) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with(
                'status',
                'Your account is awaiting approval by the Executive Officer.',
            );
        }

        return $next($request);
    }
}
