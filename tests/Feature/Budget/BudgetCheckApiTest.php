<?php

use App\Models\Department;
use App\Models\DepartmentBudget;
use App\Models\User;
use App\Services\PpmpQuarterlyTracker;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->tracker = app(PpmpQuarterlyTracker::class);
    $this->department = Department::factory()->create();
    $this->dean = User::factory()->create(['department_id' => $this->department->id]);
    $this->dean->assignRole('Dean');

    DepartmentBudget::factory()->for($this->department)->create([
        'fiscal_year' => $this->tracker->currentFiscalYear(),
        'allocated_budget' => 50_000,
        'reserved_budget' => 10_000,
    ]);
});

test('a guest cannot check the department budget', function () {
    $this->getJson(route('api.budget.check'))->assertUnauthorized();
    $this->postJson(route('api.budget.validate'), ['amount' => 100])->assertUnauthorized();
});

test('a dean can read the remaining budget for the current year', function () {
    $this->actingAs($this->dean)
        ->getJson(route('api.budget.check'))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.department_id', $this->department->id)
        ->assertJsonPath('data.fiscal_year', $this->tracker->currentFiscalYear())
        ->assertJsonPath('data.allocated_budget', 50000)
        ->assertJsonPath('data.reserved_budget', 10000)
        ->assertJsonPath('data.available_budget', 40000);
});

test('a dean can validate whether an amount can be reserved', function () {
    $this->actingAs($this->dean)
        ->postJson(route('api.budget.validate'), ['amount' => 5000])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.can_reserve', true)
        ->assertJsonPath('data.requested_amount', 5000)
        ->assertJsonPath('data.shortage', 0);

    $this->actingAs($this->dean)
        ->postJson(route('api.budget.validate'), ['amount' => 80_000])
        ->assertOk()
        ->assertJsonPath('data.can_reserve', false)
        ->assertJsonPath('data.shortage', 40000);
});
