<?php

namespace Database\Factories;

use App\Models\PurchaseRequest;
use App\Models\User;
use App\Models\WorkflowApproval;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowApproval>
 */
class WorkflowApprovalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_request_id' => PurchaseRequest::factory(),
            'step_name' => 'budget_office_earmarking',
            'step_order' => 2,
            'approver_id' => User::factory(),
            'status' => 'pending',
            'assigned_at' => now(),
        ];
    }
}
