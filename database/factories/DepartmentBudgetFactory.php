<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\DepartmentBudget;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DepartmentBudget>
 */
class DepartmentBudgetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'fiscal_year' => (int) now()->format('Y'),
            'allocated_budget' => 1_000_000,
            'utilized_budget' => 0,
            'reserved_budget' => 0,
            'notes' => null,
            'set_by' => User::factory(),
        ];
    }
}
