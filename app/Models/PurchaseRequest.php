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
use Illuminate\Database\Eloquent\Relations\MorphMany;
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
     * Fund cluster codes shown on the earmark form and Excel.
     *
     * @var array<string, string>
     */
    public const FUND_CLUSTERS = [
        '01' => 'Regular Agency Fund',
        '05' => 'Off-Budgetary Fund',
        '06' => 'Income Generating Enterprise',
        '07' => 'Trust Receipts',
    ];

    /**
     * Next control number for the given month: PR-MMYY-####.
     */
    public static function generateNextPrNumber(?Carbon $asOf = null): string
    {
        return static::generateNextControlNumber('pr_number', 'PR', $asOf);
    }

    /**
     * Next earmark number for the given month: EM-MMYY-####.
     */
    public static function generateNextEarmarkId(?Carbon $asOf = null): string
    {
        return static::generateNextControlNumber('earmark_id', 'EM', $asOf);
    }

    /**
     * Next BAC resolution number for the given month: RES-MMYY-####.
     */
    public static function generateNextResolutionNumber(?Carbon $asOf = null): string
    {
        return static::generateNextControlNumber('resolution_number', 'RES', $asOf);
    }

    /**
     * Fund cluster codes 01/05/06/07 formatted for the earmark document.
     */
    public static function formatFundingSourceFromFundCluster(?string $code, ?string $details = null): string
    {
        $source = ($code !== null && isset(self::FUND_CLUSTERS[$code]))
            ? $code.' - '.self::FUND_CLUSTERS[$code]
            : (string) $code;

        if (filled($details)) {
            $source .= ' ('.$details.')';
        }

        return $source;
    }

    /**
     * @return MorphMany<Document, $this>
     */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
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
     * @return HasMany<WorkflowApproval, $this>
     */
    public function workflowApprovals(): HasMany
    {
        return $this->hasMany(WorkflowApproval::class);
    }

    /**
     * @return BelongsTo<PurchaseRequest, $this>
     */
    public function replacesPr(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_pr_id');
    }

    /**
     * @return BelongsTo<PurchaseRequest, $this>
     */
    public function replacedByPr(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaced_by_pr_id');
    }

    public function canManageLots(): bool
    {
        return in_array($this->status, [
            PurchaseRequestStatus::Submitted,
            PurchaseRequestStatus::SupplyOfficeReview,
        ], true);
    }

    /**
     * Supply Officer actions that are valid from the current status.
     *
     * @return list<string>
     */
    public function allowedSupplyActions(): array
    {
        return match ($this->status) {
            PurchaseRequestStatus::Submitted => ['start_review'],
            PurchaseRequestStatus::SupplyOfficeReview => ['activate', 'return', 'reject', 'cancel'],
            default => [],
        };
    }

    /**
     * Budget Office actions that are valid from the current earmark state.
     *
     * @return list<string>
     */
    public function allowedBudgetActions(): array
    {
        $actions = [];

        if ($this->status === PurchaseRequestStatus::BudgetOfficeReview) {
            $actions[] = 'approve';
            $actions[] = 'reject';
        }

        if ($this->canExportEarmark()) {
            $actions[] = 'export';
        }

        if (filled($this->earmark_id)) {
            $actions[] = 'amend';
        }

        return $actions;
    }

    /**
     * Executive Officer actions that are valid from the current status.
     *
     * @return list<string>
     */
    public function allowedCeoActions(): array
    {
        return match ($this->status) {
            PurchaseRequestStatus::CeoApproval => ['approve', 'reject'],
            default => [],
        };
    }

    public function canExportEarmark(): bool
    {
        return $this->status === PurchaseRequestStatus::BudgetOfficeReview
            || filled($this->earmark_id);
    }

    /**
     * Persist earmark columns and derive funding_source from the fund cluster.
     *
     * @param  array{
     *     legal_basis: string,
     *     earmark_programs_activities: string,
     *     earmark_responsibility_center: string,
     *     earmark_date_to: string,
     *     earmark_object_expenditures: list<array{code?: string|null, description: string, amount: mixed}>,
     *     fund_cluster_code: string,
     *     fund_details?: string|null,
     *     budget_code?: string|null,
     *     current_step_notes?: string|null
     * }  $fields
     */
    public function fillEarmarkFields(array $fields): void
    {
        $code = $fields['fund_cluster_code'];
        $details = $fields['fund_details'] ?? null;

        $this->forceFill([
            'legal_basis' => $fields['legal_basis'],
            'earmark_programs_activities' => $fields['earmark_programs_activities'],
            'earmark_responsibility_center' => $fields['earmark_responsibility_center'],
            'earmark_date_to' => $fields['earmark_date_to'],
            'earmark_object_expenditures' => $fields['earmark_object_expenditures'],
            'fund_cluster_code' => $code,
            'fund_details' => $details,
            'budget_code' => $fields['budget_code'] ?? null,
            'current_step_notes' => $fields['current_step_notes'] ?? null,
            'funding_source' => static::formatFundingSourceFromFundCluster($code, $details),
        ]);
    }

    public function ensureEarmarkId(): string
    {
        if (filled($this->earmark_id)) {
            return (string) $this->earmark_id;
        }

        $this->earmark_id = static::generateNextEarmarkId();

        return $this->earmark_id;
    }

    /**
     * Sum of quotable line qty × unit cost (lot headers and standalones).
     * Falls back to the stored header total when items have not been written
     * yet (the observer `created` hook).
     */
    public function calculateTotalCost(): float
    {
        $items = $this->relationLoaded('items')
            ? $this->items
            : $this->items()->get();

        $quotable = $items->filter(
            fn (PurchaseRequestItem $item): bool => $item->parent_lot_id === null,
        );

        if ($quotable->isNotEmpty()) {
            return (float) $quotable->sum(
                fn (PurchaseRequestItem $item): float => (float) $item->quantity_requested * (float) $item->estimated_unit_cost,
            );
        }

        return (float) $this->estimated_total;
    }

    public function refreshEstimatedTotal(): void
    {
        $this->unsetRelation('items');
        $this->forceFill(['estimated_total' => $this->calculateTotalCost()])->save();
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

    /**
     * Sequential control number `{kind}-MMYY-####` for the given column.
     */
    private static function generateNextControlNumber(string $column, string $kind, ?Carbon $asOf = null): string
    {
        $asOf ??= now();
        $prefix = $kind.'-'.$asOf->format('my').'-';

        $last = static::query()
            ->where($column, 'like', $prefix.'%')
            ->orderByDesc($column)
            ->value($column);

        $nextSequence = 1;

        if (is_string($last)) {
            $parts = explode('-', $last);
            $nextSequence = ((int) end($parts)) + 1;
        }

        return $prefix.str_pad((string) $nextSequence, 4, '0', STR_PAD_LEFT);
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
