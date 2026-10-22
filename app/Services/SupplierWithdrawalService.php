<?php

namespace App\Services;

use App\Models\PurchaseRequest;
use App\Models\QuotationItem;
use App\Models\SupplierWithdrawal;
use App\Models\User;
use Illuminate\Support\Collection;

class SupplierWithdrawalService
{
    public function __construct(private AoqService $aoqService, private PurchaseRequestActivityLogger $activityLogger) {}

    /**
     * @return array{can_withdraw: bool, reason: ?string}
     */
    public function canWithdraw(QuotationItem $quotationItem): array
    {
        if (! $quotationItem->is_winner) {
            return ['can_withdraw' => false, 'reason' => 'Only winning quotations can be withdrawn.'];
        }

        if ($quotationItem->isWithdrawn()) {
            return ['can_withdraw' => false, 'reason' => 'This quotation has already been withdrawn.'];
        }

        if (! $quotationItem->isQuoted()) {
            return ['can_withdraw' => false, 'reason' => 'Cannot withdraw an unquoted item.'];
        }

        if ($quotationItem->isDisqualified()) {
            return ['can_withdraw' => false, 'reason' => 'Cannot withdraw a disqualified quotation.'];
        }

        $purchaseRequest = $quotationItem->quotation->purchaseRequest;

        if (! in_array($purchaseRequest->status, ['bac_evaluation', 'bac_approved'], true)) {
            return ['can_withdraw' => false, 'reason' => 'Withdrawals are only allowed during BAC evaluation or after approval.'];
        }

        return ['can_withdraw' => true, 'reason' => null];
    }

    /**
     * @return array{success: bool, has_successor?: bool, successor?: mixed, withdrawal?: mixed, message: string}
     */
    public function withdraw(QuotationItem $quotationItem, string $reason, User $processedBy): array
    {
        $validation = $this->canWithdraw($quotationItem);

        if (! $validation['can_withdraw']) {
            return [
                'success' => false,
                'message' => $validation['reason'] ?? 'Withdrawal is not allowed.',
            ];
        }

        $result = $this->aoqService->processSupplierWithdrawal($quotationItem, $reason, $processedBy);

        if ($result['success']) {
            $quotationItem->loadMissing(['quotation.supplier', 'quotation.purchaseRequest']);
            $successorName = $result['has_successor']
                ? $result['successor']?->quotation?->supplier?->business_name
                : null;

            $this->activityLogger->log(
                $quotationItem->quotation->purchaseRequest,
                'supplier_withdrawal',
                $successorName
                    ? "Supplier {$quotationItem->quotation->supplier->business_name} withdrew. {$successorName} succeeded."
                    : "Supplier {$quotationItem->quotation->supplier->business_name} withdrew. No eligible successor.",
                newValue: [
                    'reason' => $reason,
                    'successor' => $successorName,
                ],
                prItemGroupId: $quotationItem->quotation->pr_item_group_id,
            );
        }

        return $result;
    }

    /**
     * @return array{supplier: string, unit_price: mixed, total_price: mixed}|null
     */
    public function getNextBidderPreview(QuotationItem $quotationItem): ?array
    {
        $next = $quotationItem->getNextRankedBidder();

        if ($next === null) {
            return null;
        }

        $next->loadMissing(['quotation.supplier']);

        return [
            'supplier' => $next->quotation->supplier->business_name,
            'unit_price' => $next->unit_price,
            'total_price' => $next->total_price,
        ];
    }

    public function wouldCauseFailure(QuotationItem $quotationItem): bool
    {
        return $quotationItem->getNextRankedBidder() === null;
    }

    /**
     * @return Collection<int, SupplierWithdrawal>
     */
    public function getWithdrawalHistory(PurchaseRequest $purchaseRequest): Collection
    {
        return $this->aoqService->getWithdrawalHistory($purchaseRequest);
    }
}
