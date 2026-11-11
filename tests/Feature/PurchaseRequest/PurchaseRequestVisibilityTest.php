<?php

use App\Models\Department;
use App\Models\PurchaseRequest;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->department = Department::factory()->create();
    $this->otherDepartment = Department::factory()->create();

    $this->owner = User::factory()->create(['department_id' => $this->department->id]);
    $this->owner->assignRole('End User');

    $this->colleague = User::factory()->create(['department_id' => $this->department->id]);
    $this->colleague->assignRole('End User');

    $this->ownPr = PurchaseRequest::factory()->create([
        'department_id' => $this->department->id,
        'requester_id' => $this->owner->id,
    ]);
    $this->colleaguePr = PurchaseRequest::factory()->create([
        'department_id' => $this->department->id,
        'requester_id' => $this->colleague->id,
    ]);
    $this->otherDepartmentPr = PurchaseRequest::factory()->create([
        'department_id' => $this->otherDepartment->id,
        'requester_id' => User::factory()->create(['department_id' => $this->otherDepartment->id])->id,
    ]);
});

test('a requester only sees their own purchase requests in the list', function () {
    $this->actingAs($this->owner)
        ->get(route('purchase-requests.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->count('purchaseRequests.data', 1)
            ->where('purchaseRequests.data.0.id', $this->ownPr->id)
        );
});

test('a requester can open their own purchase request but not a colleague\'s or another department\'s', function () {
    $this->actingAs($this->owner)
        ->get(route('purchase-requests.show', $this->ownPr))
        ->assertOk();

    $this->actingAs($this->owner)
        ->get(route('purchase-requests.show', $this->colleaguePr))
        ->assertForbidden();

    $this->actingAs($this->owner)
        ->get(route('purchase-requests.show', $this->otherDepartmentPr))
        ->assertForbidden();
});

test('a requester cannot export a purchase request they do not own', function () {
    $this->actingAs($this->owner)
        ->get(route('purchase-requests.export', $this->colleaguePr))
        ->assertForbidden();
});

test('a dean sees every purchase request in their own department only', function () {
    $dean = User::factory()->create(['department_id' => $this->department->id]);
    $dean->assignRole('Dean');

    $this->actingAs($dean)
        ->get(route('purchase-requests.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->count('purchaseRequests.data', 2));

    $this->actingAs($dean)
        ->get(route('purchase-requests.show', $this->colleaguePr))
        ->assertOk();

    $this->actingAs($dean)
        ->get(route('purchase-requests.show', $this->otherDepartmentPr))
        ->assertForbidden();
});

test('purchase request handlers can see every department\'s requests', function (string $role) {
    $handler = User::factory()->create(['department_id' => null]);
    $handler->assignRole($role);

    $this->actingAs($handler)
        ->get(route('purchase-requests.show', $this->otherDepartmentPr))
        ->assertOk();
})->with(['Supply Officer', 'Budget Office', 'Accounting Office', 'BAC Chair']);

test('the pr report is limited to the deans own department', function () {
    $dean = User::factory()->create(['department_id' => $this->department->id]);
    $dean->assignRole('Dean');

    $this->actingAs($dean)
        ->get(route('reports.pr'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->count('purchaseRequests.data', 2));
});
