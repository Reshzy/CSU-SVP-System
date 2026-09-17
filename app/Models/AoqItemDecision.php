<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'purchase_request_id',
    'purchase_request_item_id',
    'winning_quotation_item_id',
    'decision_type',
    'justification',
    'decided_by',
    'decided_at',
    'is_active',
])]
class AoqItemDecision extends Model
{
    /**
     * @return BelongsTo<PurchaseRequestItem, $this>
     */
    public function purchaseRequestItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequestItem::class);
    }

    /**
     * @return BelongsTo<QuotationItem, $this>
     */
    public function winningQuotationItem(): BelongsTo
    {
        return $this->belongsTo(QuotationItem::class, 'winning_quotation_item_id');
    }

    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }
}
