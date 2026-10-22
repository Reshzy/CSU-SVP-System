<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseRequest>
 */
class PurchaseRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pr_title' => fake()->sentence(4),
            'department_id' => Department::factory(),
            'requester_id' => User::factory(),
            'status' => 'supply_office_review',
            'is_archived' => false,
            'purpose' => fake()->paragraph(),
            'justification' => fake()->sentence(),
            'date_needed' => now()->addWeeks(2)->toDateString(),
            'estimated_total' => 0,
            'fund_cluster_code' => '01',
            'funding_source' => PurchaseRequest::formatFundingSourceFromFundCluster('01'),
            'has_ppmp' => true,
            'submitted_at' => now(),
        ];
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_archived' => true,
        ]);
    }

    public function withStatus(string $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
        ]);
    }
}
