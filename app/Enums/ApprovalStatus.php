<?php

namespace App\Enums;

/**
 * Approval lifecycle shared by `users.approval_status` and
 * `department_requests.status`, which use the same value set.
 */
enum ApprovalStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }
}
