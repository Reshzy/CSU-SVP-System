<?php

namespace App\Models;

use App\Enums\PurchaseRequestStatus;
use App\Observers\PurchaseRequestObserver;
use Database\Factories\PurchaseRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A department purchase request. New rows start at supply-office review;
 * the observer stamps the quarter, reserves the department envelope, and
 * writes the created/submitted timeline entries.
 *
 * @property int $id
 * @property string|null $pr_number
 * @property string|null $pr_title
 * @property int $department_id
 * @property int $requester_id
 * @property int|null $current_handler_id
 * @property int|null $returned_by
 * @property int|null $rejected_by
 * @property string $purpose
 * @property string|null $justification
 * @property Carbon|null $date_needed
 * @property string $estimated_total
 * @property string|null $funding_source
 * @property string|null $fund_cluster_code
 * @property string|null $fund_details
 * @property string|null $budget_code
 * @property string|null $procurement_type
 * @property string|null $procurement_method
 * @property Carbon|null $procurement_method_set_at
 * @property int|null $procurement_method_set_by
 * @property PurchaseRequestStatus $status
 * @property int|null $pr_quarter
 * @property bool $is_archived
 * @property string|null $current_step_notes
 * @property bool $has_ppmp
 * @property string|null $ppmp_reference
 * @property string|null $earmark_id
 * @property string|null $legal_basis
 * @property string|null $earmark_programs_activities
 * @property string|null $earmark_responsibility_center
 * @property Carbon|null $earmark_date_to
 * @property array<int, mixed>|null $earmark_object_expenditures
 * @property string|null $resolution_number
 * @property string|null $rfq_number
 * @property int|null $replaces_pr_id
 * @property int|null $replaced_by_pr_id
 * @property string|null $return_remarks
 * @property string|null $rejection_reason
 * @property Carbon|null $submitted_at
 * @property Carbon|null $approved_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $returned_at
 * @property Carbon|null $rejected_at
 * @property Carbon|null $status_updated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'pr_number',
    'pr_title',
    'department_id',
    'requester_id',
    'current_handler_id',
    'returned_by',
    'rejected_by',
    'purpose',
    'justification',
    'date_needed',
    'estimated_total',
    'funding_source',
    'fund_cluster_code',
    'fund_details',
    'budget_code',
    'procurement_type',
    'procurement_method',
    'procurement_method_set_at',
    'procurement_method_set_by',
    'status',
    'pr_quarter',
    'is_archived',
    'current_step_notes',
    'has_ppmp',
    'ppmp_reference',
    'earmark_id',
    'legal_basis',
    'earmark_programs_activities',
    'earmark_responsibility_center',
    'earmark_date_to',
    'earmark_object_expenditures',
    'resolution_number',
    'rfq_number',
    'replaces_pr_id',
    'replaced_by_pr_id',
    'return_remarks',
    'rejection_reason',
    'submitted_at',
    'approved_at',
    'completed_at',
    'returned_at',
    'rejected_at',
    'status_updated_at',
])]
#[ObservedBy([PurchaseRequestObserver::class])]
class PurchaseRequest extends Model
{
    /** @use HasFactory<PurchaseRequestFactory> */
    use HasFactory;

    /**
     * Next control number for the given month: PR-MMYY-####.
     */
    public static function generateNextPrNumber(?Carbon $asOf = null): string
    {
        $asOf ??= now();
        $prefix = 'PR-'.$asOf->format('my').'-';

        $last = static::query()
            ->where('pr_number', 'like', $prefix.'%')
            ->orderByDesc('pr_number')
            ->value('pr_number');

        $nextSequence = 1;

        if (is_string($last)) {
            $parts = explode('-', $last);
            $nextSequence = ((int) end($parts)) + 1;
        }

        return $prefix.str_pad((string) $nextSequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function currentHandler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_handler_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function returnedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * @return HasMany<PurchaseRequestItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseRequestItem::class);
    }

    /**
     * @return HasMany<PurchaseRequestActivity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(PurchaseRequestActivity::class);
    }

    /**
     * Sum of line qty × unit cost. Falls back to the stored header total
     * when items have not been written yet (the observer `created` hook).
     */
    public function calculateTotalCost(): float
    {
        if ($this->relationLoaded('items') ? $this->items->isNotEmpty() : $this->items()->exists()) {
            return (float) $this->items->sum(
                fn (PurchaseRequestItem $item): float => (float) $item->quantity_requested * (float) $item->estimated_unit_cost,
            );
        }

        return (float) $this->estimated_total;
    }

    /**
     * Hold the request total against the department envelope for the
     * calendar year the row was created.
     */
    public function reserveDepartmentBudget(): bool
    {
        return $this->departmentBudgetForCreatedYear()->reserveBudget($this->calculateTotalCost());
    }

    public function utilizeDepartmentBudget(): bool
    {
        return $this->departmentBudgetForCreatedYear()->utilizeBudget($this->calculateTotalCost());
    }

    public function releaseReservedBudget(): bool
    {
        return $this->departmentBudgetForCreatedYear()->releaseReservedBudget($this->calculateTotalCost());
    }

    private function departmentBudgetForCreatedYear(): DepartmentBudget
    {
        $fiscalYear = $this->created_at?->year ?? (int) date('Y');

        return DepartmentBudget::getOrCreateForDepartment($this->department_id, $fiscalYear);
    }

    protected function casts(): array
    {
        return [
            'status' => PurchaseRequestStatus::class,
            'is_archived' => 'boolean',
            'has_ppmp' => 'boolean',
            'pr_quarter' => 'integer',
            'estimated_total' => 'decimal:2',
            'date_needed' => 'date',
            'earmark_date_to' => 'date',
            'earmark_object_expenditures' => 'array',
            'procurement_method_set_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'completed_at' => 'datetime',
            'returned_at' => 'datetime',
            'rejected_at' => 'datetime',
            'status_updated_at' => 'datetime',
        ];
    }
}
