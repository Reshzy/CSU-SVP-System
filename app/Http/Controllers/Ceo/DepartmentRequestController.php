<?php

namespace App\Http\Controllers\Ceo;

use App\Concerns\FlashesToasts;
use App\Enums\ApprovalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ceo\RejectionRequest;
use App\Models\Department;
use App\Models\DepartmentRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Executive Officer's queue for guest-submitted department requests.
 */
class DepartmentRequestController extends Controller
{
    use FlashesToasts;

    public function index(Request $request): Response
    {
        $status = ApprovalStatus::tryFrom((string) $request->query('status')) ?? ApprovalStatus::Pending;

        return Inertia::render('ceo/department-requests/index', [
            'departmentRequests' => DepartmentRequest::query()
                ->where('status', $status)
                ->orderByDesc('created_at')
                ->paginate(15)
                ->withQueryString(),
            'filters' => ['status' => $status->value],
            'statusCounts' => DepartmentRequest::query()
                ->select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status'),
        ]);
    }

    public function show(DepartmentRequest $departmentRequest): Response
    {
        $departmentRequest->load('reviewer:id,name');

        return Inertia::render('ceo/department-requests/show', [
            'departmentRequest' => $departmentRequest,
        ]);
    }

    /**
     * Turn the request into a real department.
     */
    public function approve(Request $request, DepartmentRequest $departmentRequest): RedirectResponse
    {
        if (! $departmentRequest->isPending()) {
            $this->toast('That request has already been reviewed.', 'info');

            return back();
        }

        $this->guardAgainstCollisions($departmentRequest);

        DB::transaction(function () use ($request, $departmentRequest): void {
            Department::create($departmentRequest->departmentAttributes());

            $departmentRequest->forceFill([
                'status' => ApprovalStatus::Approved,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ])->save();
        });

        $this->toast("Created {$departmentRequest->name}.");

        return redirect()->route('ceo.department-requests.index');
    }

    public function reject(RejectionRequest $request, DepartmentRequest $departmentRequest): RedirectResponse
    {
        if (! $departmentRequest->isPending()) {
            $this->toast('That request has already been reviewed.', 'info');

            return back();
        }

        $departmentRequest->forceFill([
            'status' => ApprovalStatus::Rejected,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'rejection_reason' => $request->validated('rejection_reason'),
        ])->save();

        $this->toast("Rejected {$departmentRequest->name}.", 'warning');

        return redirect()->route('ceo.department-requests.index');
    }

    /**
     * A department with the same name or code may have been created directly
     * while the request sat in the queue; `departments` has unique indexes on
     * both, so surface that as a validation error rather than a 500.
     */
    private function guardAgainstCollisions(DepartmentRequest $departmentRequest): void
    {
        $conflicts = [];

        if (Department::where('name', $departmentRequest->name)->exists()) {
            $conflicts['name'] = 'A department with that name already exists. Reject this request instead.';
        }

        if (Department::where('code', $departmentRequest->code)->exists()) {
            $conflicts['code'] = 'A department with that code already exists. Reject this request instead.';
        }

        if ($conflicts !== []) {
            throw ValidationException::withMessages($conflicts);
        }
    }
}
