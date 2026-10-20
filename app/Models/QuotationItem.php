<?php

namespace App\Models;

use Database\Factories\QuotationItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'quotation_id',
    'purchase_request_item_id',
    'unit_price',
    'total_price',
    'is_within_abc',
    'rank',
    'is_lowest',
    'is_tied',
    'is_winner',
    'disqualification_reason',
    'is_withdrawn',
    'withdrawn_at',
    'withdrawal_reason',
])]
class QuotationItem extends Model
{
    /** @use HasFactory<QuotationItemFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Quotation, $this>
     */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /**
     * @return BelongsTo<PurchaseRequestItem, $this>
     */
    public function purchaseRequestItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequestItem::class);
    }

    public function isWithinAbc(): bool
    {
        $abc = (float) ($this->purchaseRequestItem?->estimated_unit_cost ?? 0);

        return (float) $this->unit_price <= $abc;
    }

    public function isQuoted(): bool
    {
        return $this->unit_price !== null;
    }

    public function isDisqualified(): bool
    {
        return filled($this->disqualification_reason);
    }

    public function isWithdrawn(): bool
    {
        return $this->is_withdrawn === true;
    }

    public function canWithdraw(): bool
    {
        return $this->is_winner
            && ! $this->isWithdrawn()
            && $this->isQuoted()
            && ! $this->isDisqualified();
    }

    public function withdraw(string $reason): bool
    {
        if (! $this->canWithdraw()) {
            return false;
        }

        $this->update([
            'is_withdrawn' => true,
            'withdrawn_at' => now(),
            'withdrawal_reason' => $reason,
            'is_winner' => false,
        ]);

        return true;
    }

    public function getNextRankedBidder(): ?self
    {
        return static::query()
            ->where('purchase_request_item_id', $this->purchase_request_item_id)
            ->where('id', '!=', $this->id)
            ->eligible()
            ->where('is_winner', false)
            ->orderBy('rank')
            ->first();
    }

    /**
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function eligible(Builder $query): void
    {
        $query->where('is_withdrawn', false)
            ->whereNotNull('unit_price')
            ->where('is_within_abc', true)
            ->where(function (Builder $nested): void {
                $nested->whereNull('disqualification_reason')
                    ->orWhere('disqualification_reason', '');
            });
    }

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
            'is_within_abc' => 'boolean',
            'rank' => 'integer',
            'is_lowest' => 'boolean',
            'is_tied' => 'boolean',
            'is_winner' => 'boolean',
            'is_withdrawn' => 'boolean',
            'withdrawn_at' => 'datetime',
        ];
    }
}
