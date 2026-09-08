<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use Database\Factories\DepartmentRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A guest-submitted request for a department missing from the register dropdown.
 * The Executive Officer approves it into a `departments` row, or rejects it with
 * a reason.
 *
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string|null $description
 * @property string|null $head_name
 * @property string|null $contact_person
 * @property string|null $contact_email
 * @property string|null $contact_number
 * @property string $requester_email
 * @property ApprovalStatus $status
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $rejection_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name',
    'code',
    'description',
    'head_name',
    'contact_person',
    'contact_email',
    'contact_number',
    'requester_email',
])]
class DepartmentRequest extends Model
{
    /** @use HasFactory<DepartmentRequestFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === ApprovalStatus::Pending;
    }

    /**
     * The attributes copied onto the `departments` row on approval.
     *
     * @return array<string, string|null>
     */
    public function departmentAttributes(): array
    {
        return [
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'head_name' => $this->head_name,
            'contact_person' => $this->contact_person,
            'contact_email' => $this->contact_email,
            'contact_number' => $this->contact_number,
        ];
    }

    protected function casts(): array
    {
        return [
            'status' => ApprovalStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }
}
