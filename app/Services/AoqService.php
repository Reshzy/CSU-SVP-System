<?php

namespace App\Services;

use App\Models\AoqGeneration;
use App\Models\AoqItemDecision;
use App\Models\PrItemGroup;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\QuotationItem;
use App\Models\SupplierWithdrawal;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;

class AoqService
{
    public function __construct(private PurchaseRequestActivityLogger $activityLogger) {}

    /**
     * Rank eligible quotes per item, mark ties, and create auto decisions.
     *
     * @return array{ties: list<int>, winners: list<int>}
     */
    public function calculateWinnersAndTies(PurchaseRequest $purchaseRequest, ?PrItemGroup $itemGroup = null): array
    {
        $quotableQuery = $itemGroup === null
            ? $purchaseRequest->quotableItems()
            : $itemGroup->items()->whereNull('parent_lot_id');

        $quotableItems = $quotableQuery
            ->where('procurement_status', '!=', 'failed')
            ->with('quotationItems')
            ->get();
        $ties = [];
        $winners = [];

        foreach ($quotableItems as $prItem) {
            $candidates = QuotationItem::query()
                ->where('purchase_request_item_id', $prItem->id)
                ->where('is_withdrawn', false)
                ->whereNotNull('unit_price')
                ->with(['quotation', 'purchaseRequestItem'])
                ->get()
                ->filter(function (QuotationItem $item) {
                    $withinAbc = (float) $item->unit_price <= (float) $item->purchaseRequestItem->estimated_unit_cost;
                    $item->is_within_abc = $withinAbc;

                    if (! $withinAbc) {
                        $item->disqualification_reason = 'Exceeds ABC';
                        $item->is_lowest = false;
                        $item->is_tied = false;
                        $item->is_winner = false;
                        $item->save();

                        $item->quotation->update([
                            'exceeds_abc' => true,
                            'bac_status' => 'non_compliant',
                        ]);

                        return false;
                    }

                    $item->disqualification_reason = null;
                    $item->save();

                    return true;
                })
                ->sortBy(fn (QuotationItem $item) => (float) $item->total_price)
                ->values();

            if ($candidates->isEmpty()) {
                continue;
            }

            $lowest = (float) $candidates->first()->total_price;
            $lowestGroup = $candidates->filter(fn (QuotationItem $item) => (float) $item->total_price === $lowest);

            foreach ($candidates as $index => $candidate) {
                $candidate->rank = $index + 1;
                $candidate->is_lowest = (float) $candidate->total_price === $lowest;
                $candidate->is_tied = $candidate->is_lowest && $lowestGroup->count() > 1;
                $candidate->is_winner = false;
                $candidate->save();
            }

            $existingManualDecision = AoqItemDecision::query()
                ->where('purchase_request_item_id', $prItem->id)
                ->where('is_active', true)
                ->whereIn('decision_type', ['tie_resolution', 'bac_override'])
                ->first();

            AoqItemDecision::query()
                ->where('purchase_request_item_id', $prItem->id)
                ->where('is_active', true)
                ->where('decision_type', 'auto')
                ->update(['is_active' => false]);

            if ($lowestGroup->count() > 1) {
                if ($existingManualDecision === null) {
                    $ties[] = $prItem->id;
                } else {
                    QuotationItem::query()
                        ->where('purchase_request_item_id', $prItem->id)
                        ->update(['is_winner' => false]);

                    $existingManualDecision->winningQuotationItem?->update(['is_winner' => true]);
                    $winners[] = $existingManualDecision->winning_quotation_item_id;
                }

                continue;
            }

            $winner = $lowestGroup->first();
            $winner->is_winner = true;
            $winner->save();
            $winners[] = $winner->id;

            AoqItemDecision::query()->create([
                'purchase_request_id' => $purchaseRequest->id,
                'purchase_request_item_id' => $prItem->id,
                'winning_quotation_item_id' => $winner->id,
                'decision_type' => 'auto',
                'justification' => 'Lowest complying bid',
                'decided_by' => auth()->id(),
                'decided_at' => now(),
                'is_active' => true,
            ]);
        }

        return ['ties' => $ties, 'winners' => $winners];
    }

    public function unresolvedTies(PurchaseRequest $purchaseRequest): Collection
    {
        $result = $this->calculateWinnersAndTies($purchaseRequest);

        return collect($result['ties']);
    }

    public function resolveTie(
        PurchaseRequest $purchaseRequest,
        PurchaseRequestItem $item,
        QuotationItem $winningQuotationItem,
        string $justification,
        int $userId,
    ): AoqItemDecision {
        if (mb_strlen(trim($justification)) < 10) {
            throw ValidationException::withMessages([
                'justification' => 'Tie resolution justification must be at least 10 characters.',
            ]);
        }

        return DB::transaction(function () use ($purchaseRequest, $item, $winningQuotationItem, $justification, $userId) {
            AoqItemDecision::query()
                ->where('purchase_request_item_id', $item->id)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            QuotationItem::query()
                ->where('purchase_request_item_id', $item->id)
                ->update(['is_winner' => false]);

            $winningQuotationItem->update(['is_winner' => true, 'is_tied' => false]);

            $decision = AoqItemDecision::query()->create([
                'purchase_request_id' => $purchaseRequest->id,
                'purchase_request_item_id' => $item->id,
                'winning_quotation_item_id' => $winningQuotationItem->id,
                'decision_type' => 'tie_resolution',
                'justification' => $justification,
                'decided_by' => $userId,
                'decided_at' => now(),
                'is_active' => true,
            ]);

            $this->activityLogger->log($purchaseRequest, 'tie_resolved', "Tie resolved for item {$item->id}.");

            return $decision;
        });
    }

    public function applyBacOverride(
        PurchaseRequest $purchaseRequest,
        PurchaseRequestItem $item,
        QuotationItem $winningQuotationItem,
        string $justification,
        int $userId,
    ): AoqItemDecision {
        if (mb_strlen(trim($justification)) < 20) {
            throw ValidationException::withMessages([
                'justification' => 'BAC override justification must be at least 20 characters.',
            ]);
        }

        return DB::transaction(function () use ($purchaseRequest, $item, $winningQuotationItem, $justification, $userId) {
            AoqItemDecision::query()
                ->where('purchase_request_item_id', $item->id)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            QuotationItem::query()
                ->where('purchase_request_item_id', $item->id)
                ->update(['is_winner' => false]);

            $winningQuotationItem->update(['is_winner' => true]);

            $decision = AoqItemDecision::query()->create([
                'purchase_request_id' => $purchaseRequest->id,
                'purchase_request_item_id' => $item->id,
                'winning_quotation_item_id' => $winningQuotationItem->id,
                'decision_type' => 'bac_override',
                'justification' => $justification,
                'decided_by' => $userId,
                'decided_at' => now(),
                'is_active' => true,
            ]);

            $this->activityLogger->log($purchaseRequest, 'bac_override', "BAC override applied for item {$item->id}.");

            return $decision;
        });
    }

    public function generateAoqDocument(PurchaseRequest $purchaseRequest): AoqGeneration
    {
        $ties = $this->unresolvedTies($purchaseRequest);

        if ($ties->isNotEmpty()) {
            throw ValidationException::withMessages([
                'aoq' => 'Unresolved ties block AOQ generation. Resolve ties first.',
            ]);
        }

        $reference = $this->nextAoqNumber();
        $snapshot = [
            'pr_number' => $purchaseRequest->pr_number,
            'generated_at' => now()->toIso8601String(),
            'items' => $purchaseRequest->quotableItems()->with(['quotationItems.quotation.supplier'])->get()->toArray(),
        ];

        $phpWord = new PhpWord;
        $phpWord->setDefaultFontName('Century Gothic');
        $phpWord->setDefaultFontSize(7);
        $section = $phpWord->addSection([
            'orientation' => 'landscape',
            'pageSizeW' => 12240,
            'pageSizeH' => 15840,
        ]);
        $section->addText('ABSTRACT OF QUOTATIONS', ['bold' => true, 'size' => 11], ['alignment' => Jc::CENTER]);
        $section->addText("AOQ Reference: {$reference}");
        $section->addText("PR: {$purchaseRequest->pr_number}");

        $relativePath = 'aoq_documents/AOQ_'.$reference.'_'.now()->format('Ymd_His').'.docx';
        $absolutePath = Storage::disk('local')->path($relativePath);
        $directory = dirname($absolutePath);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        IOFactory::createWriter($phpWord, 'Word2007')->save($absolutePath);

        $generation = AoqGeneration::query()->create([
            'aoq_reference_number' => $reference,
            'purchase_request_id' => $purchaseRequest->id,
            'generated_by' => auth()->id(),
            'document_hash' => hash_file('sha256', $absolutePath),
            'exported_data_snapshot' => $snapshot,
            'file_path' => $relativePath,
            'file_format' => 'docx',
            'supplier_count' => $purchaseRequest->quotations()->count(),
            'item_count' => $purchaseRequest->quotableItems()->count(),
        ]);

        $this->activityLogger->log($purchaseRequest, 'aoq_generated', "AOQ {$reference} generated.");

        return $generation;
    }

    /**
     * @return array{can_generate: bool, errors: list<string>}
     */
    public function canGenerateAoqForGroup(PrItemGroup $itemGroup): array
    {
        $errors = [];

        if (! $itemGroup->canCreateAoq() && ! $itemGroup->canRegenerateAoq()) {
            $errors[] = 'This group already has an AOQ or Purchase Order created.';
        }

        if ($itemGroup->quotations()->count() === 0) {
            $errors[] = 'No quotations have been submitted for this group yet.';
        }

        foreach ($itemGroup->items()->whereNull('parent_lot_id')->get() as $item) {
            if ($item->procurement_status === 'failed') {
                continue;
            }

            $hasQuote = QuotationItem::query()
                ->where('purchase_request_item_id', $item->id)
                ->whereNotNull('unit_price')
                ->where('is_withdrawn', false)
                ->exists();

            if (! $hasQuote) {
                $errors[] = "Item '{$item->item_name}' has no valid quotations.";
            }
        }

        $result = $this->calculateWinnersAndTies($itemGroup->purchaseRequest, $itemGroup);

        if ($result['ties'] !== []) {
            $errors[] = 'Unresolved ties block AOQ generation. Resolve ties first.';
        }

        return [
            'can_generate' => $errors === [],
            'errors' => $errors,
        ];
    }

    public function generateAoqDocumentForGroup(PrItemGroup $itemGroup): AoqGeneration
    {
        $purchaseRequest = $itemGroup->purchaseRequest;
        $validation = $this->canGenerateAoqForGroup($itemGroup);

        if (! $validation['can_generate']) {
            throw ValidationException::withMessages([
                'aoq' => implode(' ', $validation['errors']),
            ]);
        }

        $existing = $itemGroup->aoqGeneration;

        if ($existing !== null) {
            if (filled($existing->file_path) && Storage::disk('local')->exists($existing->file_path)) {
                Storage::disk('local')->delete($existing->file_path);
            }

            $existing->delete();
        }

        $this->calculateWinnersAndTies($purchaseRequest, $itemGroup);

        $reference = $this->nextAoqNumber();
        $snapshot = [
            'pr_number' => $purchaseRequest->pr_number,
            'group_code' => $itemGroup->group_code,
            'generated_at' => now()->toIso8601String(),
            'items' => $itemGroup->items()->whereNull('parent_lot_id')->with(['quotationItems.quotation.supplier'])->get()->toArray(),
        ];

        $relativePath = 'aoq_documents/AOQ_'.$reference.'_'.$itemGroup->group_code.'_'.now()->format('Ymd_His').'.docx';
        $this->writeAoqDocument($relativePath, $reference, $purchaseRequest, $itemGroup->group_code);

        $generation = AoqGeneration::query()->create([
            'aoq_reference_number' => $reference,
            'purchase_request_id' => $purchaseRequest->id,
            'pr_item_group_id' => $itemGroup->id,
            'generated_by' => auth()->id(),
            'document_hash' => hash_file('sha256', Storage::disk('local')->path($relativePath)),
            'exported_data_snapshot' => $snapshot,
            'file_path' => $relativePath,
            'file_format' => 'docx',
            'supplier_count' => $itemGroup->quotations()->count(),
            'item_count' => $itemGroup->items()->whereNull('parent_lot_id')->count(),
        ]);

        $this->activityLogger->log(
            $purchaseRequest,
            'aoq_generated',
            "AOQ {$reference} generated for {$itemGroup->group_code}.",
            prItemGroupId: $itemGroup->id,
        );

        $purchaseRequest->unsetRelation('itemGroups');
        $purchaseRequest->syncStatusFromGroups();

        return $generation;
    }

    public function generateConsolidatedAoq(PurchaseRequest $purchaseRequest): AoqGeneration
    {
        if ($purchaseRequest->itemGroups()->count() === 0) {
            throw ValidationException::withMessages([
                'aoq' => 'This PR does not have item groups. Use the regular AOQ generation instead.',
            ]);
        }

        foreach ($purchaseRequest->itemGroups as $group) {
            $result = $this->calculateWinnersAndTies($purchaseRequest, $group);

            if ($result['ties'] !== []) {
                throw ValidationException::withMessages([
                    'aoq' => "Unresolved ties in {$group->group_code} block consolidated AOQ generation.",
                ]);
            }
        }

        $reference = $this->nextAoqNumber();
        $snapshot = [
            'pr_number' => $purchaseRequest->pr_number,
            'notes' => 'Consolidated AOQ for all item groups.',
            'generated_at' => now()->toIso8601String(),
        ];

        $relativePath = 'aoq_documents/AOQ_'.$reference.'_consolidated_'.now()->format('Ymd_His').'.docx';
        $this->writeAoqDocument($relativePath, $reference, $purchaseRequest, 'CONSOLIDATED');

        $generation = AoqGeneration::query()->create([
            'aoq_reference_number' => $reference,
            'purchase_request_id' => $purchaseRequest->id,
            'pr_item_group_id' => null,
            'generated_by' => auth()->id(),
            'document_hash' => hash_file('sha256', Storage::disk('local')->path($relativePath)),
            'exported_data_snapshot' => $snapshot,
            'file_path' => $relativePath,
            'file_format' => 'docx',
            'supplier_count' => $purchaseRequest->quotations()->count(),
            'item_count' => $purchaseRequest->quotableItems()->count(),
        ]);

        $this->activityLogger->log($purchaseRequest, 'aoq_generated', "Consolidated AOQ {$reference} generated.");

        return $generation;
    }

    /**
     * @return array{success: bool, has_successor: bool, successor: ?QuotationItem, withdrawal: SupplierWithdrawal, message: string}
     */
    public function processSupplierWithdrawal(QuotationItem $quotationItem, string $reason, User $processedBy): array
    {
        return DB::transaction(function () use ($quotationItem, $reason, $processedBy) {
            $prItem = $quotationItem->purchaseRequestItem;
            $quotation = $quotationItem->quotation;
            $purchaseRequest = $quotation->purchaseRequest;

            $quotationItem->withdraw($reason);
            $successor = $quotationItem->getNextRankedBidder();

            $withdrawal = SupplierWithdrawal::query()->create([
                'quotation_item_id' => $quotationItem->id,
                'supplier_id' => $quotation->supplier_id,
                'purchase_request_item_id' => $prItem->id,
                'pr_item_group_id' => $prItem->pr_item_group_id,
                'withdrawal_reason' => $reason,
                'withdrawn_at' => now(),
                'withdrawn_by' => $processedBy->id,
                'successor_quotation_item_id' => $successor?->id,
                'resulted_in_failure' => $successor === null,
            ]);

            if ($successor !== null) {
                $this->promoteNextWinner($prItem, $successor, $purchaseRequest);

                return [
                    'success' => true,
                    'has_successor' => true,
                    'successor' => $successor,
                    'withdrawal' => $withdrawal,
                    'message' => "Withdrawal processed. {$successor->quotation->supplier->business_name} is now the winner.",
                ];
            }

            $prItem->markAsFailed('All suppliers have withdrawn or been disqualified');

            return [
                'success' => true,
                'has_successor' => false,
                'successor' => null,
                'withdrawal' => $withdrawal,
                'message' => 'Withdrawal processed. No eligible bidders remaining - item marked as failed procurement.',
            ];
        });
    }

    public function handleFailedProcurement(
        Collection $failedItems,
        PurchaseRequest $originalPr,
    ): ?PurchaseRequest {
        $quotableFailedItems = $failedItems
            ->filter(fn (PurchaseRequestItem $item): bool => $item->parent_lot_id === null)
            ->values();

        if ($quotableFailedItems->isEmpty()) {
            return null;
        }

        return DB::transaction(function () use ($quotableFailedItems, $originalPr) {
            $quotableFailedItems->each(
                fn (PurchaseRequestItem $item): PurchaseRequestItem => $item->loadMissing('lotChildren'),
            );

            $totalCost = $quotableFailedItems->sum(function (PurchaseRequestItem $item): float {
                return (float) $item->quantity_requested * (float) $item->estimated_unit_cost;
            });

            $replacementPr = PurchaseRequest::query()->create([
                'requester_id' => $originalPr->requester_id,
                'department_id' => $originalPr->department_id,
                'purpose' => "Replacement PR for failed items from {$originalPr->pr_number}",
                'justification' => 'Auto-created replacement PR. Original items failed procurement due to all suppliers withdrawing.',
                'date_needed' => $originalPr->date_needed,
                'estimated_total' => $totalCost,
                'funding_source' => $originalPr->funding_source,
                'fund_cluster_code' => $originalPr->fund_cluster_code,
                'fund_details' => $originalPr->fund_details,
                'procurement_type' => $originalPr->procurement_type,
                'procurement_method' => null,
                'status' => 'supply_office_review',
                'submitted_at' => now(),
                'status_updated_at' => now(),
                'has_ppmp' => $originalPr->has_ppmp,
                'ppmp_reference' => $originalPr->ppmp_reference,
                'replaces_pr_id' => $originalPr->id,
            ]);

            foreach ($quotableFailedItems as $originalItem) {
                $copiedItem = $this->copyFailedItem($originalItem, $replacementPr);
                $originalItem->linkToReplacementPr($replacementPr);

                if ($originalItem->isLotHeader()) {
                    foreach ($originalItem->lotChildren as $child) {
                        $this->copyFailedItem($child, $replacementPr, $copiedItem->id);
                        $child->linkToReplacementPr($replacementPr);
                    }
                }
            }

            $this->activityLogger->log(
                $replacementPr,
                'replacement_created',
                "Replacement for failed items from {$originalPr->pr_number}.",
                newValue: ['replaces_pr_id' => $originalPr->id],
            );

            return $replacementPr;
        });
    }

    /**
     * @return Collection<int, PurchaseRequestItem>
     */
    public function getFailedItemsNeedingRePr(PurchaseRequest $purchaseRequest): Collection
    {
        return $purchaseRequest->items()->needsRePr()->whereNull('parent_lot_id')->get();
    }

    public function getWithdrawalHistory(PurchaseRequest $purchaseRequest): Collection
    {
        $itemIds = $purchaseRequest->items()->pluck('id');

        return SupplierWithdrawal::query()
            ->whereIn('purchase_request_item_id', $itemIds)
            ->with(['supplier', 'quotationItem', 'successorQuotationItem.quotation.supplier', 'withdrawnBy'])
            ->orderByDesc('withdrawn_at')
            ->get();
    }

    private function copyFailedItem(
        PurchaseRequestItem $originalItem,
        PurchaseRequest $replacementPr,
        ?int $parentLotId = null,
    ): PurchaseRequestItem {
        return PurchaseRequestItem::query()->create([
            'purchase_request_id' => $replacementPr->id,
            'ppmp_item_id' => $originalItem->ppmp_item_id,
            'ppmp_quarter' => $originalItem->ppmp_quarter,
            'ppmp_planned_qty_for_quarter' => $originalItem->ppmp_planned_qty_for_quarter,
            'ppmp_remaining_qty_at_creation' => $originalItem->ppmp_remaining_qty_at_creation,
            'item_code' => $originalItem->item_code,
            'item_name' => $originalItem->getRawOriginal('item_name') ?? $originalItem->item_name,
            'detailed_specifications' => $originalItem->detailed_specifications,
            'unit_of_measure' => $originalItem->getRawOriginal('unit_of_measure') ?? $originalItem->unit_of_measure,
            'quantity_requested' => $originalItem->quantity_requested,
            'estimated_unit_cost' => $originalItem->estimated_unit_cost,
            'estimated_total_cost' => $originalItem->estimated_total_cost,
            'item_category' => $originalItem->item_category,
            'item_status' => 'pending',
            'procurement_status' => 'pending',
            'is_lot' => $originalItem->is_lot,
            'lot_name' => $originalItem->lot_name,
            'parent_lot_id' => $parentLotId,
        ]);
    }

    private function promoteNextWinner(
        PurchaseRequestItem $prItem,
        QuotationItem $newWinner,
        PurchaseRequest $purchaseRequest,
    ): void {
        AoqItemDecision::query()
            ->where('purchase_request_item_id', $prItem->id)
            ->where('is_active', true)
            ->delete();

        QuotationItem::query()
            ->where('purchase_request_item_id', $prItem->id)
            ->update(['is_winner' => false]);

        $newWinner->update(['is_winner' => true]);

        AoqItemDecision::query()->create([
            'purchase_request_id' => $purchaseRequest->id,
            'purchase_request_item_id' => $prItem->id,
            'winning_quotation_item_id' => $newWinner->id,
            'decision_type' => 'withdrawal_succession',
            'justification' => 'Next eligible bidder after supplier withdrawal',
            'decided_by' => auth()->id(),
            'decided_at' => now(),
            'is_active' => true,
        ]);
    }

    private function writeAoqDocument(string $relativePath, string $reference, PurchaseRequest $purchaseRequest, ?string $groupLabel = null): void
    {
        $phpWord = new PhpWord;
        $phpWord->setDefaultFontName('Century Gothic');
        $phpWord->setDefaultFontSize(7);
        $section = $phpWord->addSection([
            'orientation' => 'landscape',
            'pageSizeW' => 12240,
            'pageSizeH' => 15840,
        ]);
        $section->addText('ABSTRACT OF QUOTATIONS', ['bold' => true, 'size' => 11], ['alignment' => Jc::CENTER]);
        $section->addText("AOQ Reference: {$reference}");
        $section->addText("PR: {$purchaseRequest->pr_number}");

        if (filled($groupLabel)) {
            $section->addText("Group: {$groupLabel}");
        }

        $absolutePath = Storage::disk('local')->path($relativePath);
        $directory = dirname($absolutePath);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        IOFactory::createWriter($phpWord, 'Word2007')->save($absolutePath);
    }

    private function nextAoqNumber(): string
    {
        $mmyy = now()->format('my');
        $pattern = "AOQ-{$mmyy}-";
        $latest = AoqGeneration::query()->where('aoq_reference_number', 'like', $pattern.'%')->orderByDesc('aoq_reference_number')->value('aoq_reference_number');
        $sequence = 1;

        if (is_string($latest) && preg_match('/-(\d{4})$/', $latest, $matches) === 1) {
            $sequence = (int) $matches[1] + 1;
        }

        return $pattern.sprintf('%04d', $sequence);
    }
}
