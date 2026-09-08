<?php

namespace App\Models;

use Database\Factories\DepartmentBudgetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A department's fiscal-year envelope. Available funds are allocated minus
 * what live purchase requests have reserved or already utilized.
 *
 * @property int $id
 * @property int $department_id
 * @property int $fiscal_year
 * @property string $allocated_budget
 * @property string $utilized_budget
 * @property string $reserved_budget
 * @property string|null $notes
 * @property int|null $set_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'department_id',
    'fiscal_year',
    'allocated_budget',
    'utilized_budget',
    'reserved_budget',
    'notes',
    'set_by',
])]
class DepartmentBudget extends Model
{
    /** @use HasFactory<DepartmentBudgetFactory> */
    use HasFactory;

    /**
     * The envelope for a department and year, created as zeros on first use
     * so reserve/check never fail for a missing row.
     */
    public static function getOrCreateForDepartment(int $departmentId, int $fiscalYear): self
    {
        return static::firstOrCreate(
            [
                'department_id' => $departmentId,
                'fiscal_year' => $fiscalYear,
            ],
            [
                'allocated_budget' => 0,
                'utilized_budget' => 0,
                'reserved_budget' => 0,
            ],
        );
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
    public function setBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by');
    }

    public function getAvailableBudget(): float
    {
        return (float) $this->allocated_budget - (float) $this->utilized_budget - (float) $this->reserved_budget;
    }

    public function getCommittedBudget(): float
    {
        return (float) $this->utilized_budget + (float) $this->reserved_budget;
    }

    public function canReserve(float $amount): bool
    {
        return $this->getAvailableBudget() >= $amount;
    }

    /**
     * Hold funds for a non-draft purchase request. Returns false when the
     * envelope cannot cover the amount; the caller logs rather than throws.
     */
    public function reserveBudget(float $amount): bool
    {
        if (! $this->canReserve($amount)) {
            return false;
        }

        $this->reserved_budget = (float) $this->reserved_budget + $amount;

        return $this->save();
    }

    /**
     * Move reserved funds into utilized when a request completes.
     */
    public function utilizeBudget(float $amount): bool
    {
        $this->reserved_budget = max(0, (float) $this->reserved_budget - $amount);
        $this->utilized_budget = (float) $this->utilized_budget + $amount;

        return $this->save();
    }

    /**
     * Give reserved funds back when a request is cancelled, rejected, or returned.
     */
    public function releaseReservedBudget(float $amount): bool
    {
        $this->reserved_budget = max(0, (float) $this->reserved_budget - $amount);

        return $this->save();
    }

    public function getUtilizationPercentage(): float
    {
        if ((float) $this->allocated_budget <= 0) {
            return 0.0;
        }

        return ((float) $this->utilized_budget / (float) $this->allocated_budget) * 100;
    }

    protected function casts(): array
    {
        return [
            'fiscal_year' => 'integer',
            'allocated_budget' => 'decimal:2',
            'utilized_budget' => 'decimal:2',
            'reserved_budget' => 'decimal:2',
        ];
    }
}
