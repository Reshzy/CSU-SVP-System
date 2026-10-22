<?php

use App\Models\Department;
use App\Models\PurchaseRequest;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->department = Department::factory()->create();
    $this->dean = User::factory()->create(['department_id' => $this->department->id]);
    $this->dean->assignRole('Dean');
});

test('the purchase request show page includes the activity timeline', function () {
    $purchaseRequest = PurchaseRequest::factory()->create([
        'department_id' => $this->department->id,
        'requester_id' => $this->dean->id,
        'status' => 'supply_office_review',
    ]);

    $this->actingAs($this->dean)
        ->get(route('purchase-requests.show', $purchaseRequest))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('purchase-requests/show')
            ->has('purchaseRequest.activities')
            ->where('purchaseRequest.activities.0.action', 'created')
            ->where('purchaseRequest.activities.1.action', 'submitted')
        );
});
