<?php

namespace App\Models;

use Database\Factories\PurchaseRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * Purchase request hub. Created at supply_office_review (not draft).
 *
 * @property int $id
 * @property string|null $pr_number
 * @property string|null $pr_title
 * @property int $department_id
 * @property int $requester_id
 * @property int|null $current_handler_id
 * @property int|null $returned_by
 * @property int|null $rejected_by
 * @property string $status
 * @property int|null $pr_quarter
 * @property bool $is_archived
 * @property string|null $purpose
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
 * @property string|null $current_step_notes
 * @property bool $has_ppmp
 * @property string|null $ppmp_reference
 * @property string|null $earmark_id
 * @property string|null $legal_basis
 * @property string|null $earmark_programs_activities
 * @property string|null $earmark_responsibility_center
 * @property Carbon|null $earmark_date_to
 * @property array<int, array<string, mixed>>|null $earmark_object_expenditures
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
    'status',
    'pr_quarter',
    'is_archived',
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
class PurchaseRequest extends Model
{
    /** @use HasFactory<PurchaseRequestFactory> */
    use HasFactory;

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
     * @return HasMany<PurchaseRequestItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseRequestItem::class);
    }

    /**
     * Quotable lines exclude lot children.
     *
     * @return HasMany<PurchaseRequestItem, $this>
     */
    public function quotableItems(): HasMany
    {
        return $this->items()->whereNull('parent_lot_id');
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
     * @return HasMany<Quotation, $this>
     */
    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    /**
     * @return MorphMany<Document, $this>
     */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function replaces(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_pr_id');
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function replacedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaced_by_pr_id');
    }

    public static function generateNextPrNumber(?Carbon $at = null): string
    {
        return static::generateNextNumber('PR', 'pr_number', $at);
    }

    public static function generateNextEarmarkId(?Carbon $at = null): string
    {
        return static::generateNextNumber('EM', 'earmark_id', $at);
    }

    public static function generateNextResolutionNumber(?Carbon $at = null): string
    {
        return static::generateNextNumber('RES', 'resolution_number', $at);
    }

    public static function generateNextRfqNumber(?Carbon $at = null): string
    {
        return static::generateNextNumber('RFQ', 'rfq_number', $at);
    }

    /**
     * Mint PREFIX-MMYY-#### using the highest existing sequence for that month.
     */
    public static function generateNextNumber(string $prefix, string $column, ?Carbon $at = null): string
    {
        $at ??= now();
        $mmyy = $at->format('my');
        $pattern = "{$prefix}-{$mmyy}-";

        $latest = static::query()
            ->where($column, 'like', $pattern.'%')
            ->orderByDesc($column)
            ->lockForUpdate()
            ->value($column);

        $sequence = 1;

        if (is_string($latest) && preg_match('/-(\d{4})$/', $latest, $matches) === 1) {
            $sequence = (int) $matches[1] + 1;
        }

        return $pattern.sprintf('%04d', $sequence);
    }

    public function calculateTotalCost(): float
    {
        return (float) $this->items
            ->whereNull('parent_lot_id')
            ->sum(function (PurchaseRequestItem $item): float {
                return (float) $item->quantity_requested * (float) $item->estimated_unit_cost;
            });
    }

    public function recalculateEstimatedTotal(): void
    {
        $this->estimated_total = $this->calculateTotalCost();
        $this->save();
    }

    public static function formatFundingSourceFromFundCluster(?string $code): ?string
    {
        return match ($code) {
            '01' => '01 - Regular Agency Fund',
            '05' => '05 - Off-Budgetary Funds',
            '06' => '06 - Internally Generated Income (IGE)',
            '07' => '07 - Trust Receipts',
            default => $code,
        };
    }

    public function hasBeenThroughBac(): bool
    {
        return in_array($this->status, ['bac_evaluation', 'bac_approved', 'partial_po_generation', 'po_generation', 'po_approved', 'supplier_processing', 'delivered', 'completed'], true)
            || filled($this->resolution_number)
            || filled($this->procurement_method);
    }

    public function canCreatePo(): bool
    {
        return in_array($this->status, ['bac_evaluation', 'bac_approved'], true);
    }

    public function statusLabel(): string
    {
        return $this->status === 'rejected' ? 'Deferred' : str_replace('_', ' ', $this->status);
    }

    protected function casts(): array
    {
        return [
            'is_archived' => 'boolean',
            'has_ppmp' => 'boolean',
            'date_needed' => 'date',
            'earmark_date_to' => 'date',
            'estimated_total' => 'decimal:2',
            'earmark_object_expenditures' => 'array',
            'procurement_method_set_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'completed_at' => 'datetime',
            'returned_at' => 'datetime',
            'rejected_at' => 'datetime',
            'status_updated_at' => 'datetime',
            'pr_quarter' => 'integer',
        ];
    }
}
