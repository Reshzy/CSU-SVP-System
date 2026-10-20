<?php

namespace App\Models;

use Database\Factories\PoSignatoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'manual_name',
    'position',
    'prefix',
    'suffix',
    'is_active',
])]
class PoSignatory extends Model
{
    /** @use HasFactory<PoSignatoryFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    #[Scope]
    protected function position(Builder $query, string $position): void
    {
        $query->where('position', $position);
    }

    public function displayName(): string
    {
        return $this->manual_name ?? ($this->user?->name ?? 'N/A');
    }

    public function formattedName(): string
    {
        $namePart = $this->prefix
            ? trim($this->prefix.' '.$this->displayName())
            : $this->displayName();

        $result = mb_strtoupper($namePart);

        if (filled($this->suffix)) {
            $result .= ', '.$this->suffix;
        }

        return $result;
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
