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
 * One line on a purchase request: a standalone item, a lot header, or a
 * child under a lot. Quotable rows are those without a parent lot.
 *
 * @property int $id
 * @property int $purchase_request_id
 * @property int|null $ppmp_item_id
 * @property bool $is_lot
 * @property string|null $lot_name
 * @property int|null $parent_lot_id
 * @property string|null $item_code
 * @property string $item_name
 * @property string|null $detailed_specifications
 * @property string $unit_of_measure
 * @property int $quantity_requested
 * @property string $estimated_unit_cost
 * @property string $estimated_total_cost
 * @property string|null $item_category
 * @property int|null $ppmp_quarter
 * @property int|null $ppmp_planned_qty_for_quarter
 * @property int|null $ppmp_remaining_qty_at_creation
 * @property string $item_status
 * @property string $procurement_status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'purchase_request_id',
    'ppmp_item_id',
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
    'ppmp_quarter',
    'ppmp_planned_qty_for_quarter',
    'ppmp_remaining_qty_at_creation',
    'item_status',
    'procurement_status',
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

    /**
     * @return BelongsTo<PurchaseRequestItem, $this>
     */
    public function parentLot(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_lot_id');
    }

    /**
     * @return HasMany<PurchaseRequestItem, $this>
     */
    public function lotChildren(): HasMany
    {
        return $this->hasMany(self::class, 'parent_lot_id');
    }

    /**
     * Bid lines: lot headers and standalones. Children are display-only.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function quotable(Builder $query): void
    {
        $query->whereNull('parent_lot_id');
    }

    protected function casts(): array
    {
        return [
            'is_lot' => 'boolean',
            'ppmp_quarter' => 'integer',
            'ppmp_planned_qty_for_quarter' => 'integer',
            'ppmp_remaining_qty_at_creation' => 'integer',
            'quantity_requested' => 'integer',
            'estimated_unit_cost' => 'decimal:2',
            'estimated_total_cost' => 'decimal:2',
        ];
    }
}
