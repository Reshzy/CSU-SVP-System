<?php

namespace Database\Factories;

use App\Enums\PpmpStatus;
use App\Models\Department;
use App\Models\Ppmp;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ppmp>
 */
class PpmpFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'fiscal_year' => (int) date('Y'),
            'status' => PpmpStatus::Draft,
            'total_estimated_cost' => 0,
        ];
    }

    /**
     * A plan locked in as the basis for purchase requests.
     */
    public function validated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PpmpStatus::Validated,
            'validated_at' => now(),
            'validated_by' => User::factory(),
        ]);
    }

    public function forFiscalYear(int $fiscalYear): static
    {
        return $this->state(fn (array $attributes) => [
            'fiscal_year' => $fiscalYear,
        ]);
    }
}
