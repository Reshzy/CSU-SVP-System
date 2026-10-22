<?php

namespace App\Models;

use Database\Factories\DisbursementVoucherFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable([
    'voucher_number',
    'purchase_order_id',
    'supplier_id',
    'amount',
    'voucher_date',
    'status',
    'prepared_by',
    'approved_by',
    'approved_at',
    'released_at',
    'paid_at',
    'remarks',
])]
class DisbursementVoucher extends Model
{
    /** @use HasFactory<DisbursementVoucherFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
    ];

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public static function generateNextVoucherNumber(?Carbon $at = null): string
    {
        $at ??= now();
        $mmyy = $at->format('my');
        $pattern = "DV-{$mmyy}-";

        $latest = static::query()
            ->where('voucher_number', 'like', $pattern.'%')
            ->orderByDesc('voucher_number')
            ->lockForUpdate()
            ->value('voucher_number');

        $sequence = 1;

        if (is_string($latest) && preg_match('/-(\d{4})$/', $latest, $matches) === 1) {
            $sequence = (int) $matches[1] + 1;
        }

        return $pattern.sprintf('%04d', $sequence);
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'voucher_date' => 'date',
            'approved_at' => 'datetime',
            'released_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }
}
