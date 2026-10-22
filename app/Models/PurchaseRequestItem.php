<?php

namespace App\Models;

use Database\Factories\PurchaseRequestItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Line item on a purchase request, including optional lot headers/children.
 *
 * @property int $id
 * @property int $purchase_request_id
 * @property int|null $ppmp_item_id
 * @property int|null $pr_item_group_id
 * @property int|null $ppmp_quarter
 * @property int|null $ppmp_planned_qty_for_quarter
 * @property int|null $ppmp_remaining_qty_at_creation
 * @property bool $is_lot
 * @property string|null $lot_name
 * @property int|null $parent_lot_id
 * @property string|null $item_code
 * @property string|null $item_name
 * @property string|null $detailed_specifications
 * @property string|null $unit_of_measure
 * @property int $quantity_requested
 * @property string $estimated_unit_cost
 * @property string $estimated_total_cost
 * @property string|null $item_category
 * @property string $item_status
 * @property string $procurement_status
 * @property int|null $replacement_pr_id
 * @property Carbon|null $failed_at
 * @property string|null $failure_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'purchase_request_id',
    'ppmp_item_id',
    'pr_item_group_id',
    'ppmp_quarter',
    'ppmp_planned_qty_for_quarter',
    'ppmp_remaining_qty_at_creation',
    'is_lot',
    'lot_name',
    'parent_lot_id',
    'item_code',
    'item_name',
    'detailed_specifications',
    'unit_of_measure',
    'quantity_requested',
    'estimated_unit_cost',
    'estimated_total_cost',
    'item_category',
    'item_status',
    'procurement_status',
    'replacement_pr_id',
    'failed_at',
    'failure_reason',
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
     * @return BelongsTo<PrItemGroup, $this>
     */
    public function itemGroup(): BelongsTo
    {
        return $this->belongsTo(PrItemGroup::class, 'pr_item_group_id');
    }

    /**
     * @return BelongsTo<PpmpItem, $this>
     */
    public function ppmpItem(): BelongsTo
    {
        return $this->belongsTo(PpmpItem::class);
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function parentLot(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_lot_id');
    }

    /**
     * @return HasMany<self, $this>
     */
    public function lotChildren(): HasMany
    {
        return $this->hasMany(self::class, 'parent_lot_id');
    }

    /**
     * @return HasMany<QuotationItem, $this>
     */
    public function quotationItems(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }

    /**
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function quotable(Builder $query): void
    {
        $query->whereNull('parent_lot_id');
    }

    /**
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function needsRePr(Builder $query): void
    {
        $query->where('procurement_status', 'failed')->whereNull('replacement_pr_id');
    }

    public function isLotHeader(): bool
    {
        return $this->is_lot && $this->parent_lot_id === null;
    }

    public function isLotChild(): bool
    {
        return $this->parent_lot_id !== null;
    }

    public function markAsFailed(string $reason): bool
    {
        $this->update([
            'procurement_status' => 'failed',
            'failed_at' => now(),
            'failure_reason' => $reason,
        ]);

        if ($this->isLotHeader()) {
            $this->lotChildren()->update([
                'procurement_status' => 'failed',
                'failed_at' => now(),
                'failure_reason' => $reason,
            ]);
        }

        return true;
    }

    public function linkToReplacementPr(PurchaseRequest $replacementPr): bool
    {
        $this->update([
            'procurement_status' => 're_pr_created',
            'replacement_pr_id' => $replacementPr->id,
        ]);

        return true;
    }

    protected function casts(): array
    {
        return [
            'ppmp_quarter' => 'integer',
            'ppmp_planned_qty_for_quarter' => 'integer',
            'ppmp_remaining_qty_at_creation' => 'integer',
            'quantity_requested' => 'integer',
            'is_lot' => 'boolean',
            'estimated_unit_cost' => 'decimal:2',
            'estimated_total_cost' => 'decimal:2',
            'failed_at' => 'datetime',
        ];
    }
}
