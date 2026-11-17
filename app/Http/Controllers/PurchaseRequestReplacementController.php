<?php

namespace App\Http\Controllers;

use App\Concerns\FlashesToasts;
use App\Http\Requests\PurchaseRequest\StorePurchaseRequestRequest;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Services\PurchaseRequestCreator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseRequestReplacementController extends Controller
{
    use FlashesToasts;

    public function create(Request $request, PurchaseRequest $purchaseRequest, PurchaseRequestCreator $creator): Response
    {
        $this->ensureReplaceable($request, $purchaseRequest);

        $purchaseRequest->load(['items', 'returnedBy:id,name']);

        return Inertia::render('purchase-requests/create', [
            ...$creator->formProps($request->user(), $purchaseRequest->id),
            'replacing' => [
                'id' => $purchaseRequest->id,
                'pr_number' => $purchaseRequest->pr_number,
                'pr_title' => $purchaseRequest->pr_title,
                'purpose' => $purchaseRequest->purpose,
                'justification' => $purchaseRequest->justification,
                'return_remarks' => $purchaseRequest->return_remarks,
                'returned_at' => $purchaseRequest->returned_at?->toIso8601String(),
                'returned_by_name' => $purchaseRequest->returnedBy?->name,
                'lots' => $purchaseRequest->items
                    ->where('is_lot', true)
                    ->map(fn (PurchaseRequestItem $lot): array => [
                        'id' => (string) $lot->id,
                        'name' => $lot->lot_name ?? $lot->item_name ?? 'Lot',
                    ])
                    ->values(),
                'lines' => $purchaseRequest->items
                    ->where('is_lot', false)
                    ->whereNotNull('ppmp_item_id')
                    ->map(fn (PurchaseRequestItem $item): array => [
                        'ppmp_item_id' => $item->ppmp_item_id,
                        'quantity_requested' => (int) $item->quantity_requested,
                        'lot_id' => $item->parent_lot_id !== null ? (string) $item->parent_lot_id : null,
                        'item_name' => (string) $item->item_name,
                        'estimated_unit_cost' => (float) $item->estimated_unit_cost,
                    ])
                    ->values(),
            ],
        ]);
    }

    public function store(
        StorePurchaseRequestRequest $request,
        PurchaseRequest $purchaseRequest,
        PurchaseRequestCreator $creator,
    ): RedirectResponse {
        $this->ensureReplaceable($request, $purchaseRequest);

        $user = $request->user();

        $replacement = $creator->create(
            $user,
            $creator->validatedPpmpFor($user) ?? abort(404),
            $request->validated('items'),
            $request->purchaseRequestAttributes(),
            replaces: $purchaseRequest,
        );

        $this->toast("Replacement {$replacement->pr_number} submitted to Supply Office.");

        return redirect()->route('purchase-requests.show', $replacement);
    }

    private function ensureReplaceable(Request $request, PurchaseRequest $purchaseRequest): void
    {
        Gate::authorize('view', $purchaseRequest);

        abort_unless($purchaseRequest->canBeReplacedBy($request->user()), 403);
    }
}
