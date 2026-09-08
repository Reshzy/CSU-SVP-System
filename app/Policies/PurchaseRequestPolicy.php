<?php

namespace App\Policies;

use App\Enums\PurchaseRequestStatus;
use App\Models\PurchaseRequest;
use App\Models\User;

/**
 * Requesters see their own requests. Supply Officers (`edit-purchase-request`)
 * see the review queue. `create` still lists System Admin via the permission
 * (and the super-admin gate); the form request is what forbids that role from
 * submitting.
 */
class PurchaseRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view-purchase-request');
    }

    public function view(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->id === $purchaseRequest->requester_id
            || $user->can('edit-purchase-request');
    }

    public function create(User $user): bool
    {
        return $user->can('create-purchase-request');
    }

    public function update(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can('edit-purchase-request');
    }

    public function createReplacement(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->id === $purchaseRequest->requester_id
            && $purchaseRequest->status === PurchaseRequestStatus::ReturnedBySupply
            && $purchaseRequest->replaced_by_pr_id === null
            && ! $purchaseRequest->is_archived;
    }
}
