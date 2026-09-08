<?php

namespace App\Http\Controllers\Ceo;

use App\Concerns\FlashesToasts;
use App\Enums\ApprovalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ceo\RejectionRequest;
use App\Models\User;
use App\Support\PositionRoleMap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Executive Officer's registration queue.
 */
class UserManagementController extends Controller
{
    use FlashesToasts;

    public function index(Request $request): Response
    {
        $status = ApprovalStatus::tryFrom((string) $request->query('status')) ?? ApprovalStatus::Pending;

        $users = User::query()
            ->with(['department:id,name,code', 'position:id,name'])
            ->where('approval_status', $status)
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = '%'.$request->string('search')->trim().'%';
                $query->where(fn ($q) => $q->where('name', 'like', $search)->orWhere('email', 'like', $search));
            })
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('ceo/users/index', [
            'users' => $users,
            'filters' => [
                'status' => $status->value,
                'search' => $request->string('search')->value(),
            ],
            'statusCounts' => User::query()
                ->select('approval_status', DB::raw('count(*) as total'))
                ->groupBy('approval_status')
                ->pluck('total', 'approval_status'),
        ]);
    }

    public function show(User $user): Response
    {
        $user->load(['department:id,name,code', 'position:id,name', 'approver:id,name', 'rejecter:id,name']);

        return Inertia::render('ceo/users/show', [
            'user' => $user,
            'roles' => $user->getRoleNames(),
            'mappedRole' => PositionRoleMap::roleFor($user->position?->name),
            'idProofs' => $user->idProofs()->get(['id', 'file_name', 'mime_type', 'file_size', 'created_at']),
        ]);
    }

    /**
     * Activate the account and give it the role its position maps to.
     *
     * The role grant is not optional: an approved user with no role is refused
     * by every `role:` middleware in the app.
     */
    public function approve(User $user): RedirectResponse
    {
        if ($user->approval_status === ApprovalStatus::Approved) {
            $this->toast('That account is already approved.', 'info');

            return back();
        }

        DB::transaction(function () use ($user): void {
            $user->forceFill([
                'approval_status' => ApprovalStatus::Approved,
                'is_active' => true,
                'approved_at' => now(),
                'approved_by' => auth()->id(),
                'rejected_at' => null,
                'rejected_by' => null,
                'rejection_reason' => null,
            ])->save();

            if ($user->roles->isEmpty()) {
                $user->assignRole(PositionRoleMap::roleFor($user->position?->name));
            }
        });

        $this->toast("Approved {$user->name}.");

        return redirect()->route('ceo.users.index');
    }

    public function reject(RejectionRequest $request, User $user): RedirectResponse
    {
        $user->forceFill([
            'approval_status' => ApprovalStatus::Rejected,
            'is_active' => false,
            'rejected_at' => now(),
            'rejected_by' => $request->user()->id,
            'rejection_reason' => $request->validated('rejection_reason'),
            'approved_at' => null,
            'approved_by' => null,
        ])->save();

        $this->toast("Rejected {$user->name}.", 'warning');

        return redirect()->route('ceo.users.index');
    }
}
