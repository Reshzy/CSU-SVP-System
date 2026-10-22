<?php

namespace App\Models;

use Database\Factories\PurchaseOrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'purchase_order_id',
    'purchase_request_item_id',
    'quotation_item_id',
    'quantity',
    'unit_price',
    'total_price',
])]
class PurchaseOrderItem extends Model
{
    /** @use HasFactory<PurchaseOrderItemFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

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
    public function quotationItem(): BelongsTo
    {
        return $this->belongsTo(QuotationItem::class);
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
        ];
    }
}
