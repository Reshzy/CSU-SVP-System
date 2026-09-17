<?php

namespace App\Services;

use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestActivity;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class PurchaseRequestActivityLogger
{
    /**
     * @param  array<string, mixed>|null  $oldValue
     * @param  array<string, mixed>|null  $newValue
     */
    public function log(
        PurchaseRequest $purchaseRequest,
        string $action,
        ?string $description = null,
        ?array $oldValue = null,
        ?array $newValue = null,
        ?int $prItemGroupId = null,
    ): PurchaseRequestActivity {
        return PurchaseRequestActivity::query()->create([
            'purchase_request_id' => $purchaseRequest->id,
            'user_id' => Auth::id(),
            'pr_item_group_id' => $prItemGroupId,
            'action' => $action,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'description' => $description,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }
}
