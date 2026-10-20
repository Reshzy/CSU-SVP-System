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
     * Effective group status. Purchase orders are Slice 8; until then a group
     * is pending or aoq_generated.
     */
    public function computeStatus(): string
    {
        return $this->aoqGeneration()->exists() ? 'aoq_generated' : 'pending';
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
