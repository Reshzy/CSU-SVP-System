<?php

namespace App\Models;

use Database\Factories\QuotationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'quotation_number',
    'purchase_request_id',
    'pr_item_group_id',
    'supplier_id',
    'supplier_location',
    'quotation_date',
    'validity_date',
    'total_amount',
    'exceeds_abc',
    'bac_status',
    'technical_score',
    'financial_score',
    'total_score',
    'is_winning_bid',
    'quotation_file_path',
    'supporting_documents',
])]
class Quotation extends Model
{
    /** @use HasFactory<QuotationFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<PurchaseRequest, $this>
     */
    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return HasMany<QuotationItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }

    protected function casts(): array
    {
        return [
            'quotation_date' => 'date',
            'validity_date' => 'date',
            'total_amount' => 'decimal:2',
            'exceeds_abc' => 'boolean',
            'technical_score' => 'decimal:2',
            'financial_score' => 'decimal:2',
            'total_score' => 'decimal:2',
            'is_winning_bid' => 'boolean',
            'supporting_documents' => 'array',
        ];
    }
}
