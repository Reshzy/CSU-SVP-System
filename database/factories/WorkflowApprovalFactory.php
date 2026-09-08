<?php

namespace Database\Factories;

use App\Enums\WorkflowApprovalStatus;
use App\Enums\WorkflowStepName;
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
            'step_name' => WorkflowStepName::BudgetOfficeEarmarking,
            'step_order' => 2,
            'approver_id' => User::factory(),
            'status' => WorkflowApprovalStatus::Pending,
            'assigned_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WorkflowApprovalStatus::Pending,
        ]);
    }
}
