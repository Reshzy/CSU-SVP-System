<?php

namespace App\Models;

use Database\Factories\InventoryReceiptItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'inventory_receipt_id',
    'description',
    'unit_of_measure',
    'quantity',
    'unit_price',
    'total_price',
])]
class InventoryReceiptItem extends Model
{
    /** @use HasFactory<InventoryReceiptItemFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<InventoryReceipt, $this>
     */
    public function inventoryReceipt(): BelongsTo
    {
        return $this->belongsTo(InventoryReceipt::class);
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
        ];
    }
}
