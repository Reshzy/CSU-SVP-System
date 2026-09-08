<?php

namespace App\Models;

use App\Enums\PpmpStatus;
use Database\Factories\PpmpFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A department's Project Procurement Management Plan for one fiscal year.
 * Purchase requests may only draw from a `Validated` plan.
 *
 * @property int $id
 * @property int $department_id
 * @property int $fiscal_year
 * @property PpmpStatus $status
 * @property string $total_estimated_cost
 * @property Carbon|null $validated_at
 * @property int|null $validated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'department_id',
    'fiscal_year',
    'status',
    'total_estimated_cost',
])]
class Ppmp extends Model
{
    /** @use HasFactory<PpmpFactory> */
    use HasFactory;

    /**
     * The single plan a department may hold for a year, created empty on first
     * use so importers and the editor share one row.
     */
    public static function getOrCreateForDepartment(int $departmentId, int $fiscalYear): self
    {
        return static::firstOrCreate(
            [
                'department_id' => $departmentId,
                'fiscal_year' => $fiscalYear,
            ],
            [
                'status' => PpmpStatus::Draft,
                'total_estimated_cost' => 0,
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
    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    /**
     * @return HasMany<PpmpItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PpmpItem::class);
    }

    public function isValidated(): bool
    {
        return $this->status === PpmpStatus::Validated;
    }

    public function calculateTotalCost(): float
    {
        return (float) $this->items()->sum('estimated_total_cost');
    }

    /**
     * Re-derive the header total from the lines. Call after any import or edit.
     */
    public function recalculateTotalCost(): void
    {
        $this->forceFill(['total_estimated_cost' => $this->calculateTotalCost()])->save();
    }

    public function markValidated(User $validator): void
    {
        $this->forceFill([
            'status' => PpmpStatus::Validated,
            'validated_at' => now(),
            'validated_by' => $validator->id,
        ])->save();
    }

    /**
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function validated(Builder $query): void
    {
        $query->where('status', PpmpStatus::Validated);
    }

    protected function casts(): array
    {
        return [
            'fiscal_year' => 'integer',
            'status' => PpmpStatus::class,
            'total_estimated_cost' => 'decimal:2',
            'validated_at' => 'datetime',
        ];
    }
}
