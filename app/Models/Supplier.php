<?php

namespace App\Models;

use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    protected function casts(): array
    {
        return [
            'performance_rating' => 'decimal:2',
            'password' => 'hashed',
        ];
    }
}
