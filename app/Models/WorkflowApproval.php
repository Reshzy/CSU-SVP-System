<?php

namespace App\Models;

use App\Enums\WorkflowApprovalStatus;
use App\Enums\WorkflowStepName;
use Database\Factories\WorkflowApprovalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A pending (or later decided) office task on a purchase request.
 *
 * @property int $id
 * @property int $purchase_request_id
 * @property WorkflowStepName $step_name
 * @property int $step_order
 * @property int|null $approver_id
 * @property int|null $approved_by
 * @property WorkflowApprovalStatus $status
 * @property string|null $comments
 * @property string|null $remarks
 * @property Carbon|null $assigned_at
 * @property Carbon|null $approved_at
 * @property Carbon|null $rejected_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'purchase_request_id',
    'step_name',
    'step_order',
    'approver_id',
    'approved_by',
    'status',
    'comments',
    'remarks',
    'assigned_at',
    'approved_at',
    'rejected_at',
])]
class WorkflowApproval extends Model
{
    /** @use HasFactory<WorkflowApprovalFactory> */
    use HasFactory;

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
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    protected function casts(): array
    {
        return [
            'step_name' => WorkflowStepName::class,
            'status' => WorkflowApprovalStatus::class,
            'step_order' => 'integer',
            'assigned_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }
}
