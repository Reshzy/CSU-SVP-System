<?php

use App\Models\Department;
use App\Models\DepartmentBudget;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->budgetOfficer = User::factory()->create();
    $this->budgetOfficer->assignRole('Budget Office');
});

test('the budget office can view department budgets', function () {
    $department = Department::factory()->create(['name' => 'College of Computing']);
    DepartmentBudget::factory()->create([
        'department_id' => $department->id,
        'fiscal_year' => 2026,
        'allocated_budget' => 25000,
    ]);

    $this->actingAs($this->budgetOfficer)
        ->get(route('budget.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('budget/departments/index')
            ->has('budgets.data', 1)
            ->where('budgets.data.0.department.name', 'College of Computing')
            ->has('filters')
            ->has('departments')
        );
});

test('the department budget list searches by department name', function () {
    $computing = Department::factory()->create(['name' => 'College of Computing', 'code' => 'CICS']);
    $agriculture = Department::factory()->create(['name' => 'College of Agriculture', 'code' => 'COA']);

    DepartmentBudget::factory()->create(['department_id' => $computing->id]);
    DepartmentBudget::factory()->create(['department_id' => $agriculture->id]);

    $this->actingAs($this->budgetOfficer)
        ->get(route('budget.index', ['search' => 'Computing']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('budget/departments/index')
            ->count('budgets.data', 1)
            ->where('budgets.data.0.department.code', 'CICS')
        );
});

test('a user without the Budget Office role cannot view department budgets', function () {
    $dean = User::factory()->create();
    $dean->assignRole('Dean');

    $this->actingAs($dean)->get(route('budget.index'))->assertForbidden();
});
