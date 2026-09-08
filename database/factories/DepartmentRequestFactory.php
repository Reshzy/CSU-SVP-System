<?php

namespace Database\Factories;

use App\Enums\ApprovalStatus;
use App\Models\DepartmentRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DepartmentRequest>
 */
class DepartmentRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'College of '.fake()->unique()->word(),
            'code' => Str::upper(fake()->unique()->bothify('???##')),
            'description' => fake()->sentence(),
            'head_name' => fake()->name(),
            'contact_person' => fake()->name(),
            'contact_email' => fake()->unique()->safeEmail(),
            'contact_number' => fake()->numerify('09#########'),
            'requester_email' => fake()->unique()->safeEmail(),
            'status' => ApprovalStatus::Pending,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ApprovalStatus::Approved,
            'reviewed_by' => User::factory(),
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ApprovalStatus::Rejected,
            'reviewed_by' => User::factory(),
            'reviewed_at' => now(),
            'rejection_reason' => fake()->sentence(),
        ]);
    }
}
