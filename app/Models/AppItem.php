<?php

namespace App\Models;

use Database\Factories\AppItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A line in the PS-DBMS reference catalog, also surfaced as the APP. There is
 * no separate `apps` table; the fiscal year on each row is what separates one
 * annual catalog from the next.
 *
 * `unit_price` is null for SOFTWARE and PART II items, which PS-DBM does not
 * price. PPMP lines for those items fall back to a price supplied by the
 * planning department.
 *
 * @property int $id
 * @property int $fiscal_year
 * @property string $category
 * @property string $item_code
 * @property string $item_name
 * @property string $unit_of_measure
 * @property string|null $unit_price
 * @property string|null $specifications
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'fiscal_year',
    'category',
    'item_code',
    'item_name',
    'unit_of_measure',
    'unit_price',
    'specifications',
    'is_active',
])]
class AppItem extends Model
{
    /** @use HasFactory<AppItemFactory> */
    use HasFactory;

    /**
     * Category fragments PS-DBM leaves unpriced.
     *
     * @var list<string>
     */
    public const UNPRICED_CATEGORY_MARKERS = ['SOFTWARE', 'PART II'];

    /**
     * SOFTWARE and PART II items are procured outside PS-DBM, so the worksheet
     * leaves their price blank and the importer keeps the row anyway.
     * Elsewhere a blank price means the row is not a real catalog entry.
     */
    public static function allowsMissingPrice(string $category): bool
    {
        foreach (self::UNPRICED_CATEGORY_MARKERS as $marker) {
            if (str_contains(mb_strtoupper($category), $marker)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return HasMany<PpmpItem, $this>
     */
    public function ppmpItems(): HasMany
    {
        return $this->hasMany(PpmpItem::class);
    }

    /**
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function forFiscalYear(Builder $query, int $fiscalYear): void
    {
        $query->where('fiscal_year', $fiscalYear);
    }

    protected function casts(): array
    {
        return [
            'fiscal_year' => 'integer',
            'unit_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
