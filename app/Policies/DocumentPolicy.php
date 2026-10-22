<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\PurchaseRequest;
use App\Models\User;

class DocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, Document $document): bool
    {
        if ($document->is_public) {
            return true;
        }

        if ($document->documentable_type === PurchaseRequest::class) {
            $purchaseRequest = $document->documentable;

            if ($purchaseRequest instanceof PurchaseRequest) {
                return $user->can('view', $purchaseRequest);
            }
        }

        if (is_array($document->visible_to_roles) && $document->visible_to_roles !== []) {
            foreach ($document->visible_to_roles as $role) {
                if ($user->hasRole($role)) {
                    return true;
                }
            }
        }

        if ($document->uploaded_by === $user->id) {
            return true;
        }

        if ($user->hasAnyRole(['System Admin', 'Executive Officer'])) {
            return true;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Document $document): bool
    {
        return false;
    }

    public function delete(User $user, Document $document): bool
    {
        return false;
    }

    public function restore(User $user, Document $document): bool
    {
        return false;
    }

    public function forceDelete(User $user, Document $document): bool
    {
        return false;
    }
}
