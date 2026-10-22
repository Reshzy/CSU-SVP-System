<?php

namespace App\Models;

use Database\Factories\RfqGenerationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable([
    'pr_item_group_id',
    'rfq_number',
    'generated_by',
    'generated_at',
    'file_path',
])]
class RfqGeneration extends Model
{
    /** @use HasFactory<RfqGenerationFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<PrItemGroup, $this>
     */
    public function itemGroup(): BelongsTo
    {
        return $this->belongsTo(PrItemGroup::class, 'pr_item_group_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public static function generateNextRfqNumber(?Carbon $at = null): string
    {
        $at ??= now();
        $mmyy = $at->format('my');
        $pattern = "RFQ-{$mmyy}-";

        $latest = static::query()
            ->where('rfq_number', 'like', $pattern.'%')
            ->orderByDesc('rfq_number')
            ->lockForUpdate()
            ->value('rfq_number');

        $sequence = 1;

        if (is_string($latest) && preg_match('/-(\d{4})$/', $latest, $matches) === 1) {
            $sequence = (int) $matches[1] + 1;
        }

        return $pattern.sprintf('%04d', $sequence);
    }

    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
        ];
    }
}
