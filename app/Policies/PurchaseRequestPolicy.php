<?php

namespace App\Policies;

use App\Models\PurchaseRequest;
use App\Models\User;

class PurchaseRequestPolicy
{
    /**
     * Roles that handle purchase requests and may see every department's PRs.
     *
     * @var list<string>
     */
    public const HANDLER_ROLES = ['Supply Officer', 'Budget Office', 'Executive Officer', 'BAC Chair', 'BAC Members', 'BAC Secretariat', 'System Admin', 'Accounting Office'];

    public static function handlesAllPurchaseRequests(User $user): bool
    {
        return $user->hasAnyRole(self::HANDLER_ROLES);
    }

    public function viewAny(User $user): bool
    {
        return $user->department_id !== null || self::handlesAllPurchaseRequests($user);
    }

    public function view(User $user, PurchaseRequest $purchaseRequest): bool
    {
        if (self::handlesAllPurchaseRequests($user)) {
            return true;
        }

        if ($purchaseRequest->requester_id === $user->id) {
            return true;
        }

        return $user->hasRole('Dean')
            && $user->department_id !== null
            && $user->department_id === $purchaseRequest->department_id;
    }

    public function create(User $user): bool
    {
        return $user->canCreatePurchaseRequests() && $user->department_id !== null;
    }
}
