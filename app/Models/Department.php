<?php

namespace App\Models;

use Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string|null $description
 * @property string|null $head_name
 * @property string|null $contact_person
 * @property string|null $contact_email
 * @property string|null $contact_number
 * @property bool $is_active
 * @property bool $is_archived
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name',
    'code',
    'description',
    'head_name',
    'contact_person',
    'contact_email',
    'contact_number',
    'is_active',
    'is_archived',
])]
class Department extends Model
{
    /** @use HasFactory<DepartmentFactory> */
    use HasFactory;

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Departments a registering user may select.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function selectable(Builder $query): void
    {
        $query->where('is_active', true)->where('is_archived', false);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_archived' => 'boolean',
        ];
    }
}
