<?php

namespace App\Http\Controllers\Auth;

use App\Enums\ApprovalStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * One-click sign-in for local development. The route is always registered so
 * tests can prove it 404s outside `local`; the controller is the real gate.
 */
class DevLoginController extends Controller
{
    public static function enabled(): bool
    {
        return app()->isLocal();
    }

    /**
     * @return list<array{id: int, name: string, email: string, role: string, department: string|null}>
     */
    public static function accounts(): array
    {
        $priority = array_flip(User::SVP_ROLE_PRIORITY);

        return User::query()
            ->with(['roles', 'department:id,code'])
            ->where('is_active', true)
            ->where('is_archived', false)
            ->where('approval_status', ApprovalStatus::Approved)
            ->get()
            ->sortBy(function (User $user) use ($priority): string {
                $rank = $priority[$user->getPrimarySVPRole()] ?? count($priority);

                return sprintf('%02d-%s', $rank, mb_strtolower($user->name));
            })
            ->values()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->getPrimarySVPRole(),
                'department' => $user->department?->code,
            ])
            ->all();
    }

    public function store(Request $request, User $user): RedirectResponse
    {
        abort_unless(self::enabled() && $user->isApproved() && ! $user->is_archived, 404);

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->intended(config('fortify.home'));
    }
}
