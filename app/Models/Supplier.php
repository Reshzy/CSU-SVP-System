<?php

namespace App\Models;

use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable([
    'supplier_code',
    'business_name',
    'business_type',
    'contact_person',
    'email',
    'phone',
    'address',
    'tin',
    'status',
    'performance_rating',
    'password',
])]
class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory;

    /**
     * @return HasMany<Quotation, $this>
     */
    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    /**
     * @return HasMany<PurchaseOrder, $this>
     */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public static function generateSupplierCode(?Carbon $at = null): string
    {
        $at ??= now();
        $prefix = 'SUP-'.$at->year.'-';

        $latest = static::query()
            ->where('supplier_code', 'like', $prefix.'%')
            ->orderByDesc('supplier_code')
            ->lockForUpdate()
            ->value('supplier_code');

        $sequence = 1;

        if (is_string($latest) && preg_match('/-(\d{4})$/', $latest, $matches) === 1) {
            $sequence = (int) $matches[1] + 1;
        }

        return $prefix.sprintf('%04d', $sequence);
    }

    protected function casts(): array
    {
        return [
            'performance_rating' => 'decimal:2',
            'password' => 'hashed',
        ];
    }
}
