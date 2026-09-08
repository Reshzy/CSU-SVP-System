<?php

namespace App\Services;

use App\Enums\PurchaseRequestStatus;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestActivity;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class PurchaseRequestActivityLogger
{
    public function logCreation(PurchaseRequest $purchaseRequest, ?int $userId = null): PurchaseRequestActivity
    {
        return $this->log($purchaseRequest, [
            'action' => 'created',
            'description' => 'Purchase request created',
            'user_id' => $userId ?? Auth::id(),
        ]);
    }

    public function logSubmission(PurchaseRequest $purchaseRequest, ?int $userId = null): PurchaseRequestActivity
    {
        return $this->log($purchaseRequest, [
            'action' => 'submitted',
            'description' => 'Purchase request submitted for review',
            'user_id' => $userId ?? Auth::id(),
        ]);
    }

    public function logStatusChange(
        PurchaseRequest $purchaseRequest,
        PurchaseRequestStatus|string $oldStatus,
        PurchaseRequestStatus|string $newStatus,
        ?int $userId = null,
    ): PurchaseRequestActivity {
        $old = $oldStatus instanceof PurchaseRequestStatus ? $oldStatus : PurchaseRequestStatus::from($oldStatus);
        $new = $newStatus instanceof PurchaseRequestStatus ? $newStatus : PurchaseRequestStatus::from($newStatus);

        return $this->log($purchaseRequest, [
            'action' => 'status_changed',
            'old_value' => ['status' => $old->value],
            'new_value' => ['status' => $new->value],
            'description' => "Status changed from {$old->label()} to {$new->label()}",
            'user_id' => $userId ?? Auth::id(),
        ]);
    }

    public function logReturn(PurchaseRequest $purchaseRequest, string $remarks, ?int $userId = null): PurchaseRequestActivity
    {
        return $this->log($purchaseRequest, [
            'action' => 'returned',
            'new_value' => ['return_remarks' => $remarks],
            'description' => 'Purchase request returned to department with remarks',
            'user_id' => $userId ?? Auth::id(),
        ]);
    }

    public function logRejection(PurchaseRequest $purchaseRequest, string $reason, ?int $userId = null): PurchaseRequestActivity
    {
        return $this->log($purchaseRequest, [
            'action' => 'rejected',
            'new_value' => ['rejection_reason' => $reason],
            'description' => 'Purchase request deferred',
            'user_id' => $userId ?? Auth::id(),
        ]);
    }

    public function logNotesAdded(PurchaseRequest $purchaseRequest, string $notes, ?int $userId = null): PurchaseRequestActivity
    {
        return $this->log($purchaseRequest, [
            'action' => 'notes_added',
            'new_value' => ['notes' => $notes],
            'description' => 'Notes added to purchase request',
            'user_id' => $userId ?? Auth::id(),
        ]);
    }

    public function logAssignment(PurchaseRequest $purchaseRequest, int $handlerId, ?int $userId = null): PurchaseRequestActivity
    {
        return $this->log($purchaseRequest, [
            'action' => 'assigned',
            'new_value' => ['current_handler_id' => $handlerId],
            'description' => 'Purchase request assigned to handler',
            'user_id' => $userId ?? Auth::id(),
        ]);
    }

    public function logReplacementCreated(
        PurchaseRequest $original,
        PurchaseRequest $replacement,
        ?int $userId = null,
    ): PurchaseRequestActivity {
        return $this->log($original, [
            'action' => 'replacement_created',
            'new_value' => [
                'replacement_pr_id' => $replacement->id,
                'replacement_pr_number' => $replacement->pr_number,
            ],
            'description' => 'Replacement purchase request created',
            'user_id' => $userId ?? Auth::id(),
        ]);
    }

    public function logUpdated(PurchaseRequest $purchaseRequest, string $description, ?int $userId = null): PurchaseRequestActivity
    {
        return $this->log($purchaseRequest, [
            'action' => 'updated',
            'description' => $description,
            'user_id' => $userId ?? Auth::id(),
        ]);
    }

    /**
     * @param  array{action: string, description: string, user_id?: int|null, old_value?: array<string, mixed>|null, new_value?: array<string, mixed>|null, pr_item_group_id?: int|null}  $data
     */
    public function log(PurchaseRequest $purchaseRequest, array $data): PurchaseRequestActivity
    {
        return PurchaseRequestActivity::query()->create([
            'purchase_request_id' => $purchaseRequest->id,
            'user_id' => $data['user_id'] ?? null,
            'pr_item_group_id' => $data['pr_item_group_id'] ?? null,
            'action' => $data['action'],
            'old_value' => $data['old_value'] ?? null,
            'new_value' => $data['new_value'] ?? null,
            'description' => $data['description'],
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'created_at' => now(),
        ]);
    }
}
