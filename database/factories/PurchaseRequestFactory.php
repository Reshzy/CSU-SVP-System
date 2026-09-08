<?php

namespace Database\Factories;

use App\Enums\PurchaseRequestStatus;
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
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pr_number' => PurchaseRequest::generateNextPrNumber(),
            'department_id' => Department::factory(),
            'requester_id' => User::factory(),
            'purpose' => fake()->sentence(4),
            'justification' => fake()->sentence(),
            'estimated_total' => 0,
            'status' => PurchaseRequestStatus::SupplyOfficeReview,
            'is_archived' => false,
            'has_ppmp' => true,
        ];
    }

    /**
     * A request whose reserved PPMP quantity has been released.
     */
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
