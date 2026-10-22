<?php

namespace App\Models;

use Database\Factories\SupplierWithdrawalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'quotation_item_id',
    'supplier_id',
    'purchase_request_item_id',
    'pr_item_group_id',
    'withdrawal_reason',
    'withdrawn_at',
    'withdrawn_by',
    'successor_quotation_item_id',
    'resulted_in_failure',
])]
class SupplierWithdrawal extends Model
{
    /** @use HasFactory<SupplierWithdrawalFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<QuotationItem, $this>
     */
    public function quotationItem(): BelongsTo
    {
        return $this->belongsTo(QuotationItem::class);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<PurchaseRequestItem, $this>
     */
    public function purchaseRequestItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequestItem::class);
    }

    /**
     * @return BelongsTo<PrItemGroup, $this>
     */
    public function itemGroup(): BelongsTo
    {
        return $this->belongsTo(PrItemGroup::class, 'pr_item_group_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function withdrawnBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'withdrawn_by');
    }

    /**
     * @return BelongsTo<QuotationItem, $this>
     */
    public function successorQuotationItem(): BelongsTo
    {
        return $this->belongsTo(QuotationItem::class, 'successor_quotation_item_id');
    }

    /**
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function resultedInFailure(Builder $query): void
    {
        $query->where('resulted_in_failure', true);
    }

    protected function casts(): array
    {
        return [
            'withdrawn_at' => 'datetime',
            'resulted_in_failure' => 'boolean',
        ];
    }
}
