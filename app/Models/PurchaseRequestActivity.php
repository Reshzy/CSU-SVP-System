<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One append-only timeline entry on a purchase request.
 *
 * @property int $id
 * @property int $purchase_request_id
 * @property int|null $user_id
 * @property int|null $pr_item_group_id
 * @property string $action
 * @property array<string, mixed>|null $old_value
 * @property array<string, mixed>|null $new_value
 * @property string $description
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon|null $created_at
 */
#[Fillable([
    'purchase_request_id',
    'user_id',
    'pr_item_group_id',
    'action',
    'old_value',
    'new_value',
    'description',
    'ip_address',
    'user_agent',
    'created_at',
])]
class PurchaseRequestActivity extends Model
{
    public $timestamps = false;

    /**
     * @return BelongsTo<PurchaseRequest, $this>
     */
    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'old_value' => 'array',
            'new_value' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
