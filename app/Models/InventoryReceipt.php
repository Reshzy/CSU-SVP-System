<?php

namespace App\Models;

use Database\Factories\InventoryReceiptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'purchase_order_id',
    'received_date',
    'reference_no',
    'status',
    'notes',
    'received_by',
])]
class InventoryReceipt extends Model
{
    /** @use HasFactory<InventoryReceiptFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
    ];

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * @return HasMany<InventoryReceiptItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InventoryReceiptItem::class);
    }

    protected function casts(): array
    {
        return [
            'received_date' => 'date',
        ];
    }
}
