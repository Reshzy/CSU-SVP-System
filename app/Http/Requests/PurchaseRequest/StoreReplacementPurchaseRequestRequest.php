<?php

namespace App\Http\Requests\PurchaseRequest;

use App\Models\PurchaseRequest;

class StoreReplacementPurchaseRequestRequest extends StorePurchaseRequestRequest
{
    public function authorize(): bool
    {
        $original = $this->route('originalPr');

        if (! $original instanceof PurchaseRequest) {
            return false;
        }

        return parent::authorize() && ($this->user()?->can('createReplacement', $original) ?? false);
    }

    protected function excludedPurchaseRequestId(): ?int
    {
        $original = $this->route('originalPr');

        return $original instanceof PurchaseRequest ? $original->id : null;
    }
}
