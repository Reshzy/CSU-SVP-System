<?php

namespace App\Models;

use Database\Factories\PrItemGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'purchase_request_id',
    'group_name',
    'group_code',
    'display_order',
    'status',
])]
class PrItemGroup extends Model
{
    /** @use HasFactory<PrItemGroupFactory> */
    use HasFactory;

    /**
     * @var array<string, string>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * @return BelongsTo<PurchaseRequest, $this>
     */
    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    /**
     * @return HasMany<PurchaseRequestItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseRequestItem::class, 'pr_item_group_id');
    }

    /**
     * @return HasOne<RfqGeneration, $this>
     */
    public function rfqGeneration(): HasOne
    {
        return $this->hasOne(RfqGeneration::class, 'pr_item_group_id');
    }

    /**
     * @return HasMany<Quotation, $this>
     */
    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class, 'pr_item_group_id');
    }

    /**
     * @return HasOne<AoqGeneration, $this>
     */
    public function aoqGeneration(): HasOne
    {
        return $this->hasOne(AoqGeneration::class, 'pr_item_group_id');
    }

    /**
     * @return HasMany<PurchaseOrder, $this>
     */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'pr_item_group_id');
    }

    public static function generateNextGroupCode(PurchaseRequest $purchaseRequest): string
    {
        $lastOrder = static::query()
            ->where('purchase_request_id', $purchaseRequest->id)
            ->max('display_order');

        return 'G'.(((int) $lastOrder) + 1);
    }

    public function calculateTotalCost(): float
    {
        return (float) $this->items->sum('estimated_total_cost');
    }

    /**
     * Effective group status from AOQ and purchase orders.
     */
    public function computeStatus(): string
    {
        $purchaseOrders = $this->purchaseOrders;

        if ($purchaseOrders->isEmpty()) {
            return $this->aoqGeneration()->exists() ? 'aoq_generated' : 'pending';
        }

        if ($purchaseOrders->every(fn (PurchaseOrder $purchaseOrder): bool => $purchaseOrder->status === 'completed')) {
            return 'completed';
        }

        if ($purchaseOrders->every(fn (PurchaseOrder $purchaseOrder): bool => in_array($purchaseOrder->status, ['delivered', 'completed'], true))) {
            return 'delivered';
        }

        if ($purchaseOrders->contains(fn (PurchaseOrder $purchaseOrder): bool => in_array($purchaseOrder->status, ['sent_to_supplier', 'acknowledged_by_supplier', 'delivered', 'completed'], true))) {
            return 'processing';
        }

        if ($purchaseOrders->every(fn (PurchaseOrder $purchaseOrder): bool => in_array($purchaseOrder->status, ['approved', 'sent_to_supplier', 'acknowledged_by_supplier', 'delivered', 'completed'], true))) {
            return 'all_po_approved';
        }

        return 'po_created';
    }

    public function canCreateAoq(): bool
    {
        return $this->computeStatus() === 'pending';
    }

    public function canRegenerateAoq(): bool
    {
        return $this->computeStatus() === 'aoq_generated';
    }

    public function canCreatePo(): bool
    {
        return $this->computeStatus() === 'aoq_generated';
    }

    public function isCompleted(): bool
    {
        return $this->computeStatus() === 'completed';
    }

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
        ];
    }
}
