<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\ApprovalStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property int|null $department_id
 * @property int|null $position_id
 * @property string|null $employee_id
 * @property string|null $phone
 * @property bool $is_active
 * @property bool $is_archived
 * @property ApprovalStatus $approval_status
 * @property Carbon|null $approved_at
 * @property Carbon|null $rejected_at
 * @property int|null $approved_by
 * @property int|null $rejected_by
 * @property string|null $rejection_reason
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name',
    'email',
    'password',
    'department_id',
    'position_id',
    'employee_id',
    'phone',
])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Roles ordered by seniority, used to pick the dashboard a user lands on.
     *
     * @var list<string>
     */
    public const SVP_ROLE_PRIORITY = [
        'System Admin',
        'Executive Officer',
        'Supply Officer',
        'BAC Chair',
        'Budget Office',
        'BAC Members',
        'BAC Secretariat',
        'Canvassing Unit',
        'Accounting Office',
        'Dean',
        'End User',
        'Supplier',
    ];

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<Position, $this>
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * @return MorphMany<Document, $this>
     */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /**
     * The government ID files submitted at registration.
     *
     * @return MorphMany<Document, $this>
     */
    public function idProofs(): MorphMany
    {
        return $this->documents()->where('document_type', 'other');
    }

    /**
     * Whether the Executive Officer has cleared this account for use.
     */
    public function isApproved(): bool
    {
        return $this->approval_status === ApprovalStatus::Approved && $this->is_active;
    }

    /**
     * The highest-seniority SVP role, for nav and dashboard widgets only.
     * Authorization belongs to Spatie permissions and gates.
     */
    public function getPrimarySVPRole(): string
    {
        $names = $this->getRoleNames();

        foreach (self::SVP_ROLE_PRIORITY as $role) {
            if ($names->contains($role)) {
                return $role;
            }
        }

        return 'End User';
    }

    /**
     * System Admin is an operator account, not a requester, so it may not raise
     * purchase requests even though it passes every gate.
     *
     * `StorePurchaseRequestRequest::authorize()` (Slice 3) must call this. That
     * form request deliberately overrides `PurchaseRequestPolicy::create()`,
     * which still lists System Admin.
     */
    public function canCreatePurchaseRequests(): bool
    {
        return ! $this->hasRole('System Admin');
    }

    /**
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function awaitingApproval(Builder $query): void
    {
        $query->where('approval_status', ApprovalStatus::Pending);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'approval_status' => ApprovalStatus::class,
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'is_active' => 'boolean',
            'is_archived' => 'boolean',
        ];
    }
}
