<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only activity row for a purchase request (no updated_at).
 *
 * @property int $id
 * @property int $purchase_request_id
 * @property int|null $user_id
 * @property int|null $pr_item_group_id
 * @property string $action
 * @property array<string, mixed>|null $old_value
 * @property array<string, mixed>|null $new_value
 * @property string|null $description
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
])]
class PurchaseRequestActivity extends Model
{
    public $timestamps = false;

    protected static function booted(): void
    {
        static::creating(function (self $activity): void {
            $activity->created_at ??= now();
        });
    }

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
