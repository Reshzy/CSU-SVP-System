<?php

namespace App\Policies;

use App\Models\PurchaseRequest;
use App\Models\User;

class PurchaseRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->department_id !== null || $user->hasAnyRole(['Supply Officer', 'Budget Office', 'Executive Officer', 'BAC Chair', 'BAC Members', 'BAC Secretariat', 'System Admin']);
    }

    public function view(User $user, PurchaseRequest $purchaseRequest): bool
    {
        if ($user->hasAnyRole(['Supply Officer', 'Budget Office', 'Executive Officer', 'BAC Chair', 'BAC Members', 'BAC Secretariat', 'System Admin'])) {
            return true;
        }

        return $user->department_id === $purchaseRequest->department_id;
    }

    public function create(User $user): bool
    {
        return $user->canCreatePurchaseRequests() && $user->department_id !== null;
    }
}
