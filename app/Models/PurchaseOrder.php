<?php

namespace App\Models;

use Database\Factories\PurchaseOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

#[Fillable([
    'po_number',
    'purchase_request_id',
    'pr_item_group_id',
    'supplier_id',
    'quotation_id',
    'po_date',
    'total_amount',
    'delivery_address',
    'delivery_date_required',
    'terms_and_conditions',
    'special_instructions',
    'tin',
    'supplier_name_override',
    'funds_cluster',
    'funds_available',
    'ors_burs_no',
    'ors_burs_date',
    'status',
    'approved_by',
    'approved_at',
    'sent_to_supplier_at',
    'acknowledged_at',
    'actual_delivery_date',
    'delivery_notes',
    'delivery_complete',
])]
class PurchaseOrder extends Model
{
    /** @use HasFactory<PurchaseOrderFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending_approval',
        'delivery_complete' => false,
    ];

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
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<Quotation, $this>
     */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<PurchaseOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    /**
     * @return HasMany<InventoryReceipt, $this>
     */
    public function inventoryReceipts(): HasMany
    {
        return $this->hasMany(InventoryReceipt::class);
    }

    /**
     * @return HasMany<DisbursementVoucher, $this>
     */
    public function disbursementVouchers(): HasMany
    {
        return $this->hasMany(DisbursementVoucher::class);
    }

    /**
     * @return MorphMany<Document, $this>
     */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public static function generateNextPoNumber(?Carbon $at = null): string
    {
        $at ??= now();
        $mmyy = $at->format('my');
        $pattern = "PO-{$mmyy}-";

        $latest = static::query()
            ->where('po_number', 'like', $pattern.'%')
            ->orderByDesc('po_number')
            ->lockForUpdate()
            ->value('po_number');

        $sequence = 1;

        if (is_string($latest) && preg_match('/-(\d{4})$/', $latest, $matches) === 1) {
            $sequence = (int) $matches[1] + 1;
        }

        return $pattern.sprintf('%04d', $sequence);
    }

    protected function casts(): array
    {
        return [
            'po_date' => 'date',
            'total_amount' => 'decimal:2',
            'delivery_date_required' => 'date',
            'funds_available' => 'decimal:2',
            'ors_burs_date' => 'date',
            'approved_at' => 'datetime',
            'sent_to_supplier_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'actual_delivery_date' => 'date',
            'delivery_complete' => 'boolean',
        ];
    }
}
