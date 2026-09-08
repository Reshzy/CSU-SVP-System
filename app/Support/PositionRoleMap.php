<?php

namespace App\Support;

/**
 * Positions are job titles; Spatie roles are RBAC. This is the bridge the
 * Executive Officer's approval and `users:assign-roles` use to grant a newly
 * approved account its first role.
 */
class PositionRoleMap
{
    public const DEFAULT_ROLE = 'End User';

    /**
     * @var array<string, string>
     */
    public const MAP = [
        'System Administrator' => 'System Admin',
        'Supply Officer' => 'Supply Officer',
        'Budget Officer' => 'Budget Office',
        'Executive Officer' => 'Executive Officer',
        'BAC Chairman' => 'BAC Chair',
        'BAC Member' => 'BAC Members',
        'BAC Secretary' => 'BAC Secretariat',
        'Accounting Officer' => 'Accounting Office',
        'Canvassing Officer' => 'Canvassing Unit',
        'Dean' => 'Dean',
        'Employee' => 'End User',
    ];

    /**
     * Resolve the role for a position name. Unknown and missing positions fall
     * back to End User rather than leaving the account role-less, since role
     * middleware would otherwise lock the user out of every screen.
     */
    public static function roleFor(?string $positionName): string
    {
        return self::MAP[$positionName] ?? self::DEFAULT_ROLE;
    }
}
