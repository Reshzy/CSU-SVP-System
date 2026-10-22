<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'purchase_request_id',
    'meeting_datetime',
    'location',
    'status',
    'title',
    'agenda',
    'minutes',
    'created_by',
])]
class BacMeeting extends Model
{
    /**
     * @return BelongsTo<PurchaseRequest, $this>
     */
    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'bac_meeting_attendees')
            ->withPivot(['role_at_meeting', 'attended', 'remarks'])
            ->withTimestamps();
    }

    protected function casts(): array
    {
        return [
            'meeting_datetime' => 'datetime',
        ];
    }
}
