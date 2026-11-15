<?php

namespace App\Http\Controllers;

use App\Concerns\FlashesToasts;
use App\Http\Requests\ListQueryRequest;
use App\Http\Requests\PurchaseRequest\StorePurchaseRequestRequest;
use App\Models\PurchaseRequest;
use App\Policies\PurchaseRequestPolicy;
use App\Services\PurchaseRequestCreator;
use App\Services\PurchaseRequestExportService;
use App\Support\ListQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PurchaseRequestController extends Controller
{
    use FlashesToasts;

    public function index(ListQueryRequest $request): Response
    {
        Gate::authorize('viewAny', PurchaseRequest::class);

        $user = $request->user();

        $query = PurchaseRequest::query()
            ->with(['department:id,name,code', 'requester:id,name']);

        if (! PurchaseRequestPolicy::handlesAllPurchaseRequests($user)) {
            if ($user->hasRole('Dean')) {
                $query->where('department_id', $user->department_id);
            } else {
                $query->where('requester_id', $user->id);
            }
        }

        $statusOptions = (clone $query)
            ->reorder()
            ->select('status')
            ->distinct()
            ->orderBy('status')
            ->pluck('status')
            ->map(fn (string $status): array => [
                'value' => $status,
                'label' => $status === 'rejected' ? 'Deferred' : str_replace('_', ' ', $status),
            ])
            ->values()
            ->all();

        return Inertia::render('purchase-requests/index', [
            'purchaseRequests' => ListQuery::paginate($query, $request, [
                'searchable' => ['pr_number', 'pr_title'],
                'sortable' => [
                    'pr_number' => 'pr_number',
                    'pr_title' => 'pr_title',
                    'status' => 'status',
                    'estimated_total' => 'estimated_total',
                    'created_at' => 'created_at',
                ],
                'equals' => ['status' => 'status'],
                'dateColumn' => 'created_at',
                'defaultSort' => 'created_at',
                'defaultDirection' => 'desc',
                'defaultPerPage' => 15,
            ]),
            'filters' => ListQuery::snapshot($request, [
                'status' => $request->filled('status') ? $request->string('status')->value() : null,
            ]),
            'statusOptions' => $statusOptions,
            'canCreate' => Gate::allows('create', PurchaseRequest::class),
        ]);
    }

    public function create(Request $request, PurchaseRequestCreator $creator): Response
    {
        Gate::authorize('create', PurchaseRequest::class);

        return Inertia::render('purchase-requests/create', $creator->formProps($request->user()));
    }

    public function store(StorePurchaseRequestRequest $request, PurchaseRequestCreator $creator): RedirectResponse
    {
        Gate::authorize('create', PurchaseRequest::class);

        $user = $request->user();

        $purchaseRequest = $creator->create(
            $user,
            $creator->validatedPpmpFor($user) ?? abort(404),
            $request->validated('items'),
            $request->purchaseRequestAttributes(),
        );

        $this->toast("Purchase request {$purchaseRequest->pr_number} submitted to Supply Office.");

        return redirect()->route('purchase-requests.show', $purchaseRequest);
    }

    public function show(Request $request, PurchaseRequest $purchaseRequest, PurchaseRequestCreator $creator): Response
    {
        Gate::authorize('view', $purchaseRequest);

        $purchaseRequest->load([
            'department:id,name,code',
            'requester:id,name,email,position_id,designation',
            'requester.position:id,name',
            'items',
            'activities.user:id,name',
            'returnedBy:id,name',
            'replaces:id,pr_number',
            'replacedBy:id,pr_number',
        ]);

        return Inertia::render('purchase-requests/show', [
            'purchaseRequest' => $purchaseRequest,
            'statusLabel' => $purchaseRequest->statusLabel(),
            'canCreateReplacement' => $purchaseRequest->canBeReplacedBy($request->user()),
            'signatories' => $creator->signatories(
                $purchaseRequest->requester,
                isRequester: $purchaseRequest->requester_id === $request->user()->id,
            ),
        ]);
    }

    public function export(PurchaseRequest $purchaseRequest, PurchaseRequestExportService $exportService): BinaryFileResponse|RedirectResponse
    {
        Gate::authorize('view', $purchaseRequest);

        try {
            return $exportService->download($purchaseRequest);
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withErrors(['export' => 'Unable to export this purchase request.']);
        }
    }
}
