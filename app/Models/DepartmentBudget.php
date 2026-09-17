<?php

namespace App\Models;

use Database\Factories\DepartmentBudgetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Department fiscal-year budget with allocated / reserved / utilized buckets.
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

    public function availableBudget(): float
    {
        return (float) $this->allocated_budget - (float) $this->utilized_budget - (float) $this->reserved_budget;
    }

    public function committedBudget(): float
    {
        return (float) $this->utilized_budget + (float) $this->reserved_budget;
    }

    public function reserveBudget(float $amount): bool
    {
        if ($amount <= 0) {
            return true;
        }

        if ($this->availableBudget() < $amount) {
            return false;
        }

        $this->reserved_budget = (float) $this->reserved_budget + $amount;
        $this->save();

        return true;
    }

    public function utilizeBudget(float $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        $reserved = max(0.0, (float) $this->reserved_budget - $amount);
        $this->reserved_budget = $reserved;
        $this->utilized_budget = (float) $this->utilized_budget + $amount;
        $this->save();
    }

    public function releaseReservedBudget(float $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        $this->reserved_budget = max(0.0, (float) $this->reserved_budget - $amount);
        $this->save();
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
