<?php

use App\Models\Department;
use App\Models\PurchaseRequest;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->department = Department::factory()->create();
    $this->dean = User::factory()->create(['department_id' => $this->department->id]);
    $this->dean->assignRole('Dean');
});

test('the department list searches purchase requests by title', function () {
    PurchaseRequest::factory()->create([
        'department_id' => $this->department->id,
        'requester_id' => $this->dean->id,
        'pr_title' => 'Office ballpens',
        'pr_number' => 'PR-2026-0001',
        'created_at' => now()->subDay(),
    ]);
    PurchaseRequest::factory()->create([
        'department_id' => $this->department->id,
        'requester_id' => $this->dean->id,
        'pr_title' => 'Laptop units',
        'pr_number' => 'PR-2026-0002',
        'created_at' => now(),
    ]);

    $this->actingAs($this->dean)
        ->get(route('purchase-requests.index', ['search' => 'ballpens']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('purchase-requests/index')
            ->count('purchaseRequests.data', 1)
            ->where('purchaseRequests.data.0.pr_title', 'Office ballpens')
        );
});

test('the department list sorts by estimated total and ignores unknown sort keys', function () {
    $cheap = PurchaseRequest::factory()->create([
        'department_id' => $this->department->id,
        'requester_id' => $this->dean->id,
        'estimated_total' => 50,
        'created_at' => now()->subHours(2),
    ]);
    $expensive = PurchaseRequest::factory()->create([
        'department_id' => $this->department->id,
        'requester_id' => $this->dean->id,
        'estimated_total' => 900,
        'created_at' => now()->subHour(),
    ]);

    $this->actingAs($this->dean)
        ->get(route('purchase-requests.index', ['sort' => 'estimated_total', 'direction' => 'desc']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('purchaseRequests.data.0.id', $expensive->id)
            ->where('purchaseRequests.data.1.id', $cheap->id)
        );

    $this->actingAs($this->dean)
        ->get(route('purchase-requests.index', ['sort' => 'password']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('purchaseRequests.data.0.id', $expensive->id)
        );
});

test('the department list filters by status and date range', function () {
    $inRange = PurchaseRequest::factory()->create([
        'department_id' => $this->department->id,
        'requester_id' => $this->dean->id,
        'status' => 'supply_office_review',
        'created_at' => now()->subDays(2),
    ]);
    PurchaseRequest::factory()->create([
        'department_id' => $this->department->id,
        'requester_id' => $this->dean->id,
        'status' => 'budget_office_review',
        'created_at' => now()->subDays(2),
    ]);
    PurchaseRequest::factory()->create([
        'department_id' => $this->department->id,
        'requester_id' => $this->dean->id,
        'status' => 'supply_office_review',
        'created_at' => now()->subDays(20),
    ]);

    $this->actingAs($this->dean)
        ->get(route('purchase-requests.index', [
            'status' => 'supply_office_review',
            'date_from' => now()->subDays(5)->toDateString(),
            'date_to' => now()->toDateString(),
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->count('purchaseRequests.data', 1)
            ->where('purchaseRequests.data.0.id', $inRange->id)
        );
});
