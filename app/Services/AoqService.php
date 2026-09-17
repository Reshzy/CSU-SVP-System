<?php

namespace App\Services;

use App\Models\AoqGeneration;
use App\Models\AoqItemDecision;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\QuotationItem;
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
    public function calculateWinnersAndTies(PurchaseRequest $purchaseRequest): array
    {
        $quotableItems = $purchaseRequest->quotableItems()->with('quotationItems')->get();
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
