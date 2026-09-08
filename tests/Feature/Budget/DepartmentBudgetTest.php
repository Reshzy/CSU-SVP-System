<?php

use App\Models\Department;
use App\Models\DepartmentBudget;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->department = Department::factory()->create();
    $this->dean = User::factory()->create(['department_id' => $this->department->id]);
    $this->dean->assignRole('Dean');

    $this->budget = User::factory()->create();
    $this->budget->assignRole('Budget Office');
});

test('a dean cannot manage department budgets', function () {
    $this->actingAs($this->dean)
        ->get(route('budget.index'))
        ->assertForbidden();
});

test('budget office can set allocated budget and notes', function () {
    $this->actingAs($this->budget)
        ->put(route('budget.update', $this->department), [
            'fiscal_year' => now()->year,
            'allocated_budget' => 250000,
            'notes' => 'GAA envelope',
        ])
        ->assertRedirect();

    $budget = DepartmentBudget::query()
        ->where('department_id', $this->department->id)
        ->where('fiscal_year', now()->year)
        ->firstOrFail();

    expect((float) $budget->allocated_budget)->toBe(250000.0)
        ->and($budget->notes)->toBe('GAA envelope')
        ->and($budget->set_by)->toBe($this->budget->id)
        ->and($budget->getAvailableBudget())->toBe(250000.0)
        ->and($budget->getCommittedBudget())->toBe(0.0);
});
