<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fortify's RegisteredUserController signs the new account in unconditionally.
 * SVP registrations are pending until the Executive Officer approves them, so
 * this response tears that session back down and sends the applicant to the
 * pending notice instead of the dashboard.
 */
class RegisterResponse implements RegisterResponseContract
{
    public function toResponse($request): Response
    {
        $this->abandonSession($request);

        if ($request->wantsJson()) {
            return new JsonResponse(['status' => 'pending-approval'], 201);
        }

        return redirect()->route('register.pending');
    }

    private function abandonSession(Request $request): void
    {
        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
    }
}
