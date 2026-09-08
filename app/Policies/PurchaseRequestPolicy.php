<?php

namespace App\Policies;

use App\Models\PurchaseRequest;
use App\Models\User;

/**
 * Requesters see their own requests. `create` still lists System Admin via
 * the permission (and the super-admin gate); the form request is what
 * forbids that role from submitting.
 */
class PurchaseRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view-purchase-request');
    }

    public function view(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->id === $purchaseRequest->requester_id;
    }

    public function create(User $user): bool
    {
        return $user->can('create-purchase-request');
    }
}
