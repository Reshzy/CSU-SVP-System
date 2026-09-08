<?php

namespace App\Models;

use Database\Factories\PpmpItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One catalog item planned by quarter inside a department's PPMP. Purchase
 * request lines point back here, and what they consume is what
 * `getRemainingQuantity()` subtracts.
 *
 * @property int $id
 * @property int $ppmp_id
 * @property int $app_item_id
 * @property int $q1_quantity
 * @property int $q2_quantity
 * @property int $q3_quantity
 * @property int $q4_quantity
 * @property int $total_quantity
 * @property string $estimated_unit_cost
 * @property string $estimated_total_cost
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'ppmp_id',
    'app_item_id',
    'q1_quantity',
    'q2_quantity',
    'q3_quantity',
    'q4_quantity',
    'total_quantity',
    'estimated_unit_cost',
    'estimated_total_cost',
])]
class PpmpItem extends Model
{
    /** @use HasFactory<PpmpItemFactory> */
    use HasFactory;

    /**
     * Purchase request statuses that release the quantity they reserved.
     * A returned request keeps consuming until it is archived.
     *
     * @var list<string>
     */
    public const RELEASING_PR_STATUSES = ['rejected', 'cancelled'];

    /**
     * @return BelongsTo<Ppmp, $this>
     */
    public function ppmp(): BelongsTo
    {
        return $this->belongsTo(Ppmp::class);
    }

    /**
     * @return BelongsTo<AppItem, $this>
     */
    public function appItem(): BelongsTo
    {
        return $this->belongsTo(AppItem::class);
    }

    /**
     * @return HasMany<PurchaseRequestItem, $this>
     */
    public function purchaseRequestItems(): HasMany
    {
        return $this->hasMany(PurchaseRequestItem::class);
    }

    public function getQuarterlyQuantity(int $quarter): int
    {
        return match ($quarter) {
            1 => $this->q1_quantity,
            2 => $this->q2_quantity,
            3 => $this->q3_quantity,
            4 => $this->q4_quantity,
            default => 0,
        };
    }

    public function hasQuantityForQuarter(int $quarter): bool
    {
        return $this->getQuarterlyQuantity($quarter) > 0;
    }

    /**
     * Planned quantity minus what live purchase requests already claimed.
     *
     * Only archived, rejected, and cancelled requests give quantity back — a
     * request returned by the Supply Office still holds its share until the
     * replacement flow archives it.
     *
     * Pass `$quarter` to scope both sides to one quarter's allocation, and
     * `$excludePurchaseRequestId` when editing a request that should not count
     * against itself.
     */
    public function getRemainingQuantity(?int $quarter = null, ?int $excludePurchaseRequestId = null): int
    {
        $plannedQuantity = $quarter !== null
            ? $this->getQuarterlyQuantity($quarter)
            : $this->total_quantity;

        $usedQuantity = $this->purchaseRequestItems()
            ->when($quarter !== null, fn (Builder $query) => $query->where('ppmp_quarter', $quarter))
            ->when(
                $excludePurchaseRequestId,
                fn (Builder $query) => $query->where('purchase_request_id', '!=', $excludePurchaseRequestId),
            )
            ->whereHas('purchaseRequest', function (Builder $query): void {
                $query->where('is_archived', false)
                    ->whereNotIn('status', self::RELEASING_PR_STATUSES);
            })
            ->sum('quantity_requested');

        return max(0, $plannedQuantity - (int) $usedQuantity);
    }

    public function calculateEstimatedTotalCost(): float
    {
        return (float) $this->total_quantity * (float) $this->estimated_unit_cost;
    }

    protected function casts(): array
    {
        return [
            'q1_quantity' => 'integer',
            'q2_quantity' => 'integer',
            'q3_quantity' => 'integer',
            'q4_quantity' => 'integer',
            'total_quantity' => 'integer',
            'estimated_unit_cost' => 'decimal:2',
            'estimated_total_cost' => 'decimal:2',
        ];
    }
}
