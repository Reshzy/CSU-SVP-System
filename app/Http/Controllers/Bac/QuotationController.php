<?php

namespace App\Http\Controllers\Bac;

use App\Concerns\FlashesToasts;
use App\Http\Controllers\Controller;
use App\Http\Requests\Bac\MarkItemFailedRequest;
use App\Http\Requests\Bac\WithdrawQuotationItemRequest;
use App\Models\AoqGeneration;
use App\Models\PrItemGroup;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Supplier;
use App\Services\AoqService;
use App\Services\BacResolutionService;
use App\Services\BacRfqService;
use App\Services\PurchaseRequestActivityLogger;
use App\Services\SupplierWithdrawalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuotationController extends Controller
{
    use FlashesToasts;

    public function index(): Response
    {
        $purchaseRequests = PurchaseRequest::query()
            ->with(['department:id,name', 'requester:id,name'])
            ->whereIn('status', ['bac_evaluation', 'bac_approved'])
            ->where('is_archived', false)
            ->latest()
            ->paginate(20);

        return Inertia::render('bac/quotations/index', [
            'purchaseRequests' => $purchaseRequests,
        ]);
    }

    public function manage(PurchaseRequest $purchaseRequest, AoqService $aoqService): Response
    {
        $purchaseRequest->load([
            'quotableItems.quotationItems.quotation.supplier',
            'quotations.supplier',
            'quotations.items.purchaseRequestItem',
            'documents',
            'itemGroups.rfqGeneration',
            'itemGroups.aoqGeneration',
            'itemGroups.items',
        ]);

        return Inertia::render('bac/quotations/manage', [
            'purchaseRequest' => $purchaseRequest,
            'suppliers' => Supplier::query()->where('status', 'active')->orderBy('business_name')->get(['id', 'business_name', 'supplier_code']),
            'statusLabel' => $purchaseRequest->statusLabel(),
            'canManageGroups' => $purchaseRequest->canManageGroups(),
            'groupsLocked' => $purchaseRequest->groupsAreLocked(),
            'failedItemsNeedingRePr' => $aoqService->getFailedItemsNeedingRePr($purchaseRequest),
        ]);
    }

    public function groupQuotations(PurchaseRequest $purchaseRequest, PrItemGroup $itemGroup): Response
    {
        $purchaseRequest->load(['documents', 'itemGroups']);
        $itemGroup->load([
            'rfqGeneration',
            'aoqGeneration',
            'quotations.supplier',
            'quotations.items.purchaseRequestItem',
        ]);

        $quotableItems = $itemGroup->items()
            ->whereNull('parent_lot_id')
            ->with(['quotationItems.quotation.supplier'])
            ->get();

        return Inertia::render('bac/quotations/group', [
            'purchaseRequest' => $purchaseRequest,
            'itemGroup' => $itemGroup,
            'quotableItems' => $quotableItems,
            'suppliers' => Supplier::query()->where('status', 'active')->orderBy('business_name')->get(['id', 'business_name', 'supplier_code']),
            'statusLabel' => $purchaseRequest->statusLabel(),
            'canManageGroups' => $purchaseRequest->canManageGroups(),
        ]);
    }

    public function store(Request $request, PurchaseRequest $purchaseRequest, PurchaseRequestActivityLogger $activityLogger): RedirectResponse
    {
        if (blank($purchaseRequest->procurement_method)) {
            throw ValidationException::withMessages([
                'procurement_method' => 'Quotes cannot be stored without a procurement method.',
            ]);
        }

        $hasGroups = $purchaseRequest->itemGroups()->exists();

        $validated = $request->validate([
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'supplier_location' => ['nullable', 'string'],
            'quotation_date' => ['nullable', 'date'],
            'pr_item_group_id' => [
                Rule::requiredIf($hasGroups),
                'nullable',
                'integer',
                Rule::exists('pr_item_groups', 'id')->where('purchase_request_id', $purchaseRequest->id),
            ],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_request_item_id' => ['required', 'integer', 'exists:purchase_request_items,id'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $groupId = isset($validated['pr_item_group_id']) ? (int) $validated['pr_item_group_id'] : null;

        $duplicate = Quotation::query()
            ->where('purchase_request_id', $purchaseRequest->id)
            ->where('supplier_id', $validated['supplier_id'])
            ->when(
                $groupId === null,
                fn ($query) => $query->whereNull('pr_item_group_id'),
                fn ($query) => $query->where('pr_item_group_id', $groupId),
            )
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'supplier_id' => 'This supplier already has a quotation for this group.',
            ]);
        }

        $priced = collect($validated['items'])->filter(fn (array $item) => isset($item['unit_price']) && $item['unit_price'] !== null && $item['unit_price'] !== '');

        if ($priced->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'At least one quotable item must be priced.',
            ]);
        }

        DB::transaction(function () use ($validated, $purchaseRequest, $activityLogger, $groupId) {
            $quotation = Quotation::query()->create([
                'quotation_number' => $this->nextQuotationNumber(),
                'purchase_request_id' => $purchaseRequest->id,
                'pr_item_group_id' => $groupId,
                'supplier_id' => $validated['supplier_id'],
                'supplier_location' => $validated['supplier_location'] ?? null,
                'quotation_date' => $validated['quotation_date'] ?? now()->toDateString(),
                'bac_status' => 'pending_evaluation',
            ]);

            $total = 0.0;
            $exceeds = false;

            foreach ($validated['items'] as $row) {
                if (! isset($row['unit_price']) || $row['unit_price'] === null || $row['unit_price'] === '') {
                    continue;
                }

                $itemQuery = PurchaseRequestItem::query()
                    ->where('purchase_request_id', $purchaseRequest->id)
                    ->whereNull('parent_lot_id')
                    ->whereKey($row['purchase_request_item_id']);

                if ($groupId !== null) {
                    $itemQuery->where('pr_item_group_id', $groupId);
                }

                $prItem = $itemQuery->firstOrFail();

                $unitPrice = (float) $row['unit_price'];
                $lineTotal = $unitPrice * (float) $prItem->quantity_requested;
                $withinAbc = $unitPrice <= (float) $prItem->estimated_unit_cost;
                $exceeds = $exceeds || ! $withinAbc;
                $total += $lineTotal;

                $quotation->items()->create([
                    'purchase_request_item_id' => $prItem->id,
                    'unit_price' => $unitPrice,
                    'total_price' => $lineTotal,
                    'is_within_abc' => $withinAbc,
                    'disqualification_reason' => $withinAbc ? null : 'Exceeds ABC',
                ]);
            }

            $quotation->update([
                'total_amount' => $total,
                'exceeds_abc' => $exceeds,
                'bac_status' => $exceeds ? 'non_compliant' : 'pending_evaluation',
            ]);

            $activityLogger->log($purchaseRequest, 'quotation_submitted', "Quotation {$quotation->quotation_number} submitted.", prItemGroupId: $groupId);
        });

        $this->toast('Quotation stored.');

        return back();
    }

    public function evaluate(Request $request, Quotation $quotation, PurchaseRequestActivityLogger $activityLogger): RedirectResponse
    {
        $validated = $request->validate([
            'technical_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'financial_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'bac_status' => ['required', 'string', 'in:compliant,non_compliant,lowest_bidder'],
        ]);

        $total = ((float) $validated['technical_score'] * 0.6) + ((float) $validated['financial_score'] * 0.4);

        $quotation->update([
            ...$validated,
            'total_score' => $total,
        ]);

        $activityLogger->log($quotation->purchaseRequest, 'quotation_evaluated', "Quotation {$quotation->quotation_number} evaluated.");

        $this->toast('Quotation evaluated.');

        return back();
    }

    public function finalize(Request $request, PurchaseRequest $purchaseRequest, PurchaseRequestActivityLogger $activityLogger): RedirectResponse
    {
        if ($purchaseRequest->itemGroups()->exists()) {
            throw ValidationException::withMessages([
                'quotation_id' => 'Grouped purchase requests are finalized per group via AOQ, not as a single winning quotation.',
            ]);
        }

        $validated = $request->validate([
            'quotation_id' => ['required', 'integer', 'exists:quotations,id'],
        ]);

        $quotation = Quotation::query()
            ->where('purchase_request_id', $purchaseRequest->id)
            ->whereKey($validated['quotation_id'])
            ->firstOrFail();

        Quotation::query()
            ->where('purchase_request_id', $purchaseRequest->id)
            ->update(['is_winning_bid' => false, 'bac_status' => 'not_awarded']);

        $quotation->update([
            'is_winning_bid' => true,
            'bac_status' => 'awarded',
        ]);

        $purchaseRequest->update(['status' => 'bac_approved']);

        $activityLogger->log($purchaseRequest, 'approved', "Finalized winning quotation {$quotation->quotation_number}.");

        $this->toast('Purchase request finalized.');

        return back();
    }

    public function generateRfq(PurchaseRequest $purchaseRequest, BacRfqService $rfqService): RedirectResponse
    {
        $rfqService->generateRfq($purchaseRequest);
        $this->toast('RFQ generated.');

        return back();
    }

    public function downloadRfq(PurchaseRequest $purchaseRequest): StreamedResponse|RedirectResponse
    {
        $document = $purchaseRequest->documents()
            ->where('document_type', 'bac_rfq')
            ->where('is_current_version', true)
            ->first();

        if ($document === null || ! Storage::disk('local')->exists($document->file_path)) {
            return back()->withErrors(['rfq' => 'RFQ document not found.']);
        }

        return Storage::disk('local')->download($document->file_path, $document->file_name);
    }

    public function regenerateRfq(PurchaseRequest $purchaseRequest, BacRfqService $rfqService): RedirectResponse
    {
        $rfqService->generateRfq($purchaseRequest);
        $this->toast('RFQ regenerated.');

        return back();
    }

    public function generateRfqForGroup(PrItemGroup $itemGroup, BacRfqService $rfqService): RedirectResponse
    {
        $rfqService->generateRfqForGroup($itemGroup);
        $this->toast("RFQ generated for {$itemGroup->group_code}.");

        return back();
    }

    public function downloadRfqForGroup(PrItemGroup $itemGroup): StreamedResponse|RedirectResponse
    {
        $rfq = $itemGroup->rfqGeneration;

        if ($rfq === null || blank($rfq->file_path) || ! Storage::disk('local')->exists($rfq->file_path)) {
            return back()->withErrors(['rfq' => 'RFQ document not found.']);
        }

        return Storage::disk('local')->download($rfq->file_path, basename($rfq->file_path));
    }

    public function regenerateRfqForGroup(PrItemGroup $itemGroup, BacRfqService $rfqService): RedirectResponse
    {
        $rfqService->generateRfqForGroup($itemGroup);
        $this->toast("RFQ regenerated for {$itemGroup->group_code}.");

        return back();
    }

    public function downloadResolution(PurchaseRequest $purchaseRequest): StreamedResponse|RedirectResponse
    {
        $document = $purchaseRequest->documents()
            ->where('document_type', 'bac_resolution')
            ->where('is_current_version', true)
            ->first();

        if ($document === null || ! Storage::disk('local')->exists($document->file_path)) {
            return back()->withErrors(['resolution' => 'Resolution document not found.']);
        }

        return Storage::disk('local')->download($document->file_path, $document->file_name);
    }

    public function regenerateResolution(PurchaseRequest $purchaseRequest, BacResolutionService $resolutionService): RedirectResponse
    {
        $resolutionService->generateResolution($purchaseRequest);
        $this->toast('Resolution regenerated.');

        return back();
    }

    public function aoq(PurchaseRequest $purchaseRequest, AoqService $aoqService): Response
    {
        $purchaseRequest->load([
            'quotableItems.quotationItems.quotation.supplier',
            'quotations.supplier',
            'itemGroups.aoqGeneration',
            'itemGroups.items.quotationItems.quotation.supplier',
        ]);

        $result = $purchaseRequest->itemGroups->isEmpty()
            ? $aoqService->calculateWinnersAndTies($purchaseRequest)
            : ['ties' => [], 'winners' => []];

        $groupAoq = $purchaseRequest->itemGroups->mapWithKeys(function (PrItemGroup $group) use ($aoqService, $purchaseRequest): array {
            $groupResult = $aoqService->calculateWinnersAndTies($purchaseRequest, $group);

            return [
                $group->id => [
                    'unresolved_ties' => $groupResult['ties'],
                    'can_create_aoq' => $group->canCreateAoq(),
                    'can_regenerate_aoq' => $group->canRegenerateAoq(),
                    'latest_aoq' => $group->aoqGeneration,
                ],
            ];
        });

        return Inertia::render('bac/quotations/aoq', [
            'purchaseRequest' => $purchaseRequest,
            'unresolvedTies' => $result['ties'],
            'latestAoq' => AoqGeneration::query()
                ->where('purchase_request_id', $purchaseRequest->id)
                ->whereNull('pr_item_group_id')
                ->latest()
                ->first(),
            'groupAoq' => $groupAoq,
        ]);
    }

    public function generateAoq(PurchaseRequest $purchaseRequest, AoqService $aoqService): RedirectResponse
    {
        $aoqService->generateAoqDocument($purchaseRequest);
        $this->toast('AOQ generated.');

        return back();
    }

    public function downloadAoq(PurchaseRequest $purchaseRequest): StreamedResponse|RedirectResponse
    {
        $aoq = AoqGeneration::query()
            ->where('purchase_request_id', $purchaseRequest->id)
            ->whereNull('pr_item_group_id')
            ->latest()
            ->first();

        if ($aoq === null || blank($aoq->file_path) || ! Storage::disk('local')->exists($aoq->file_path)) {
            return back()->withErrors(['aoq' => 'AOQ document not found.']);
        }

        return Storage::disk('local')->download($aoq->file_path, 'AOQ_'.$aoq->aoq_reference_number.'.docx');
    }

    public function generateAoqForGroup(PrItemGroup $itemGroup, AoqService $aoqService): RedirectResponse
    {
        $aoqService->generateAoqDocumentForGroup($itemGroup);
        $this->toast("AOQ generated for {$itemGroup->group_code}.");

        return back();
    }

    public function downloadAoqForGroup(PrItemGroup $itemGroup): StreamedResponse|RedirectResponse
    {
        $aoq = $itemGroup->aoqGeneration;

        if ($aoq === null || blank($aoq->file_path) || ! Storage::disk('local')->exists($aoq->file_path)) {
            return back()->withErrors(['aoq' => 'AOQ document not found.']);
        }

        return Storage::disk('local')->download($aoq->file_path, 'AOQ_'.$aoq->aoq_reference_number.'.docx');
    }

    public function generateConsolidatedAoq(PurchaseRequest $purchaseRequest, AoqService $aoqService): RedirectResponse
    {
        abort_unless(
            in_array($purchaseRequest->status, ['bac_evaluation', 'bac_approved'], true),
            403,
            'Cannot generate consolidated AOQ at this stage.',
        );

        $aoqService->generateConsolidatedAoq($purchaseRequest);
        $this->toast('Consolidated AOQ generated.');

        return back();
    }

    public function resolveTie(Request $request, PurchaseRequest $purchaseRequest, AoqService $aoqService): RedirectResponse
    {
        $validated = $request->validate([
            'purchase_request_item_id' => ['required', 'integer', 'exists:purchase_request_items,id'],
            'winning_quotation_item_id' => ['required', 'integer', 'exists:quotation_items,id'],
            'justification' => ['required', 'string', 'min:10'],
        ]);

        $item = PurchaseRequestItem::query()->where('purchase_request_id', $purchaseRequest->id)->whereKey($validated['purchase_request_item_id'])->firstOrFail();
        $winning = QuotationItem::query()->whereKey($validated['winning_quotation_item_id'])->firstOrFail();

        $aoqService->resolveTie($purchaseRequest, $item, $winning, $validated['justification'], $request->user()->id);
        $this->toast('Tie resolved.');

        return back();
    }

    public function bacOverride(Request $request, PurchaseRequest $purchaseRequest, AoqService $aoqService): RedirectResponse
    {
        $validated = $request->validate([
            'purchase_request_item_id' => ['required', 'integer', 'exists:purchase_request_items,id'],
            'winning_quotation_item_id' => ['required', 'integer', 'exists:quotation_items,id'],
            'justification' => ['required', 'string', 'min:20'],
        ]);

        $item = PurchaseRequestItem::query()->where('purchase_request_id', $purchaseRequest->id)->whereKey($validated['purchase_request_item_id'])->firstOrFail();
        $winning = QuotationItem::query()->whereKey($validated['winning_quotation_item_id'])->firstOrFail();

        $aoqService->applyBacOverride($purchaseRequest, $item, $winning, $validated['justification'], $request->user()->id);
        $this->toast('BAC override applied.');

        return back();
    }

    public function processWithdrawal(WithdrawQuotationItemRequest $request, QuotationItem $quotationItem, SupplierWithdrawalService $withdrawalService): RedirectResponse
    {
        $result = $withdrawalService->withdraw($quotationItem, $request->validated('withdrawal_reason'), $request->user());

        if (! $result['success']) {
            throw ValidationException::withMessages([
                'withdrawal_reason' => $result['message'],
            ]);
        }

        $this->toast($result['message']);

        return back();
    }

    public function withdrawalPreview(QuotationItem $quotationItem, SupplierWithdrawalService $withdrawalService): JsonResponse
    {
        $quotationItem->loadMissing(['quotation.supplier', 'purchaseRequestItem']);
        $canWithdraw = $withdrawalService->canWithdraw($quotationItem);

        return response()->json([
            'can_withdraw' => $canWithdraw['can_withdraw'],
            'reason' => $canWithdraw['reason'],
            'would_cause_failure' => $withdrawalService->wouldCauseFailure($quotationItem),
            'next_bidder' => $withdrawalService->getNextBidderPreview($quotationItem),
            'current_item' => [
                'supplier' => $quotationItem->quotation->supplier->business_name,
                'unit_price' => $quotationItem->unit_price,
                'total_price' => $quotationItem->total_price,
                'item_name' => $quotationItem->purchaseRequestItem->item_name,
            ],
        ]);
    }

    public function withdrawalHistory(PurchaseRequest $purchaseRequest, SupplierWithdrawalService $withdrawalService): JsonResponse
    {
        $history = $withdrawalService->getWithdrawalHistory($purchaseRequest);

        return response()->json([
            'history' => $history->map(function ($withdrawal): array {
                return [
                    'id' => $withdrawal->id,
                    'item_name' => $withdrawal->purchaseRequestItem?->item_name,
                    'supplier' => $withdrawal->supplier?->business_name,
                    'reason' => $withdrawal->withdrawal_reason,
                    'withdrawn_at' => $withdrawal->withdrawn_at?->format('M d, Y H:i'),
                    'withdrawn_by' => $withdrawal->withdrawnBy?->name,
                    'resulted_in_failure' => $withdrawal->resulted_in_failure,
                    'successor' => $withdrawal->successorQuotationItem?->quotation?->supplier?->business_name,
                ];
            })->values(),
        ]);
    }

    public function markItemFailed(MarkItemFailedRequest $request, PurchaseRequestItem $prItem): RedirectResponse
    {
        if ($prItem->quotationItems()->eligible()->exists()) {
            throw ValidationException::withMessages([
                'failure_reason' => 'Cannot mark item as failed - there are still eligible bidders.',
            ]);
        }

        $prItem->markAsFailed($request->validated('failure_reason'));
        $this->toast("Item '{$prItem->item_name}' has been marked as failed procurement.");

        return back();
    }

    public function createReplacementPr(PurchaseRequest $purchaseRequest, AoqService $aoqService): RedirectResponse
    {
        abort_unless(
            in_array($purchaseRequest->status, ['bac_evaluation', 'bac_approved'], true),
            403,
            'Cannot create replacement PR at this stage.',
        );

        $failedItems = $aoqService->getFailedItemsNeedingRePr($purchaseRequest);

        if ($failedItems->isEmpty()) {
            throw ValidationException::withMessages([
                'replacement' => 'No failed items found that need a replacement PR.',
            ]);
        }

        $replacementPr = $aoqService->handleFailedProcurement($failedItems, $purchaseRequest);

        if ($replacementPr === null) {
            throw ValidationException::withMessages([
                'replacement' => 'Failed to create replacement PR.',
            ]);
        }

        $this->toast("Replacement PR created: {$replacementPr->pr_number}.");

        return back();
    }

    private function nextQuotationNumber(): string
    {
        $mmyy = now()->format('my');
        $pattern = "Q-{$mmyy}-";
        $latest = Quotation::query()->where('quotation_number', 'like', $pattern.'%')->orderByDesc('quotation_number')->value('quotation_number');
        $sequence = 1;

        if (is_string($latest) && preg_match('/-(\d{4})$/', $latest, $matches) === 1) {
            $sequence = (int) $matches[1] + 1;
        }

        return $pattern.sprintf('%04d', $sequence);
    }
}
