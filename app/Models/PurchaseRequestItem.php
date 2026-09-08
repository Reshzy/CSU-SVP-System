<?php

namespace App\Models;

use Database\Factories\PurchaseRequestItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Slice 2 stub. Carries the PPMP linkage that remaining-quantity math reads;
 * the catalog snapshot, lots, and item statuses land with the purchase
 * request slice.
 *
 * @property int $id
 * @property int $purchase_request_id
 * @property int|null $ppmp_item_id
 * @property int|null $ppmp_quarter
 * @property int $quantity_requested
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'purchase_request_id',
    'ppmp_item_id',
    'ppmp_quarter',
    'quantity_requested',
])]
class PurchaseRequestItem extends Model
{
    /** @use HasFactory<PurchaseRequestItemFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<PurchaseRequest, $this>
     */
    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    /**
     * @return BelongsTo<PpmpItem, $this>
     */
    public function ppmpItem(): BelongsTo
    {
        return $this->belongsTo(PpmpItem::class);
    }

    protected function casts(): array
    {
        return [
            'ppmp_quarter' => 'integer',
            'quantity_requested' => 'integer',
        ];
    }
}
