<?php

namespace App\Http\Controllers;

use App\Concerns\FlashesToasts;
use App\Http\Requests\PurchaseRequest\StorePurchaseRequestRequest;
use App\Models\Ppmp;
use App\Models\PpmpItem;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Notifications\PurchaseRequestSubmitted;
use App\Services\PpmpQuarterlyTracker;
use App\Services\PurchaseRequestActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseRequestReplacementController extends Controller
{
    use FlashesToasts;

    public function create(PurchaseRequest $purchaseRequest): Response
    {
        Gate::authorize('view', $purchaseRequest);
        abort_unless($purchaseRequest->status === 'returned_by_supply' && ! $purchaseRequest->is_archived, 403);
        abort_unless($purchaseRequest->requester_id === request()->user()->id, 403);

        $purchaseRequest->load(['items']);

        return Inertia::render('purchase-requests/replacement-create', [
            'original' => $purchaseRequest,
        ]);
    }

    public function store(
        StorePurchaseRequestRequest $request,
        PurchaseRequest $purchaseRequest,
        PurchaseRequestActivityLogger $activityLogger,
        PpmpQuarterlyTracker $tracker,
    ): RedirectResponse {
        Gate::authorize('view', $purchaseRequest);
        abort_unless($purchaseRequest->status === 'returned_by_supply' && ! $purchaseRequest->is_archived, 403);
        abort_unless($purchaseRequest->requester_id === $request->user()->id, 403);

        $user = $request->user();
        $quarter = $tracker->currentQuarter();
        $fiscalYear = $tracker->currentFiscalYear();

        $ppmp = Ppmp::query()
            ->where('department_id', $user->department_id)
            ->where('fiscal_year', $fiscalYear)
            ->where('status', 'validated')
            ->firstOrFail();

        $payloadItems = $request->validated('items');
        $estimatedTotal = 0.0;
        $resolvedRows = [];

        foreach ($payloadItems as $index => $row) {
            $ppmpItem = PpmpItem::query()->with('appItem')->whereKey($row['ppmp_item_id'])->where('ppmp_id', $ppmp->id)->firstOrFail();
            $qty = (int) $row['quantity_requested'];
            $unitCost = (float) $ppmpItem->estimated_unit_cost;
            $lineTotal = $qty * $unitCost;
            $estimatedTotal += $lineTotal;

            $resolvedRows[$index] = [
                'ppmp_item_id' => $ppmpItem->id,
                'ppmp_quarter' => $quarter,
                'ppmp_planned_qty_for_quarter' => $ppmpItem->getQuarterlyQuantity($quarter),
                'ppmp_remaining_qty_at_creation' => $ppmpItem->getRemainingQuantity($quarter),
                'item_code' => $ppmpItem->appItem?->item_code,
                'item_name' => $ppmpItem->appItem?->item_name,
                'detailed_specifications' => $ppmpItem->appItem?->specifications,
                'unit_of_measure' => $ppmpItem->appItem?->unit_of_measure ?? 'pc',
                'item_category' => $ppmpItem->appItem?->category,
                'quantity_requested' => $qty,
                'estimated_unit_cost' => $unitCost,
                'estimated_total_cost' => $lineTotal,
            ];
        }

        $replacement = DB::transaction(function () use ($request, $user, $ppmp, $resolvedRows, $estimatedTotal, $purchaseRequest, $activityLogger) {
            $replacement = PurchaseRequest::query()->create([
                ...$request->purchaseRequestAttributes(),
                'department_id' => $user->department_id,
                'requester_id' => $user->id,
                'status' => 'supply_office_review',
                'estimated_total' => $estimatedTotal,
                'ppmp_reference' => "PPMP-{$ppmp->fiscal_year}",
                'submitted_at' => now(),
                'replaces_pr_id' => $purchaseRequest->id,
            ]);

            foreach ($resolvedRows as $row) {
                $replacement->items()->create([
                    ...$row,
                    'is_lot' => false,
                    'item_status' => 'pending',
                    'procurement_status' => 'pending',
                ]);
            }

            $purchaseRequest->forceFill([
                'replaced_by_pr_id' => $replacement->id,
                'is_archived' => true,
            ])->saveQuietly();

            $activityLogger->log(
                $replacement,
                'replacement_created',
                "Replacement for {$purchaseRequest->pr_number}.",
                newValue: ['replaces_pr_id' => $purchaseRequest->id],
            );

            return $replacement;
        });

        $supplyOfficers = User::role('Supply Officer')->get();

        if ($supplyOfficers->isNotEmpty()) {
            Notification::send($supplyOfficers, new PurchaseRequestSubmitted($replacement));
        }

        $this->toast("Replacement {$replacement->pr_number} submitted.");

        return redirect()->route('purchase-requests.show', $replacement);
    }
}
