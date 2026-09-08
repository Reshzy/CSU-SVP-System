<?php

use App\Enums\PurchaseRequestStatus;
use App\Enums\WorkflowApprovalStatus;
use App\Enums\WorkflowStepName;
use App\Models\Department;
use App\Models\DepartmentBudget;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Models\WorkflowApproval;
use App\Notifications\PurchaseRequestActionRequired;
use App\Notifications\PurchaseRequestStatusUpdated;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->department = Department::factory()->create();
    $this->dean = User::factory()->create(['department_id' => $this->department->id]);
    $this->dean->assignRole('Dean');

    $this->supply = User::factory()->create();
    $this->supply->assignRole('Supply Officer');

    DepartmentBudget::factory()->for($this->department)->create([
        'fiscal_year' => now()->year,
        'allocated_budget' => 1_000_000,
    ]);
});

test('a dean cannot open the supply queue', function () {
    $this->actingAs($this->dean)
        ->get(route('supply.purchase-requests.index'))
        ->assertForbidden();
});

test('activating a request moves it to budget office review and mails both offices', function () {
    Notification::fake();

    $budgetOfficer = User::factory()->create();
    $budgetOfficer->assignRole('Budget Office');

    $pr = PurchaseRequest::factory()->create([
        'department_id' => $this->department->id,
        'requester_id' => $this->dean->id,
        'status' => PurchaseRequestStatus::SupplyOfficeReview,
        'estimated_total' => 20,
    ]);

    $this->actingAs($this->supply)
        ->post(route('supply.purchase-requests.status', $pr), [
            'action' => 'activate',
        ])
        ->assertRedirect(route('supply.purchase-requests.show', $pr));

    $pr->refresh();

    expect($pr->status)->toBe(PurchaseRequestStatus::BudgetOfficeReview);

    $approval = WorkflowApproval::query()->where('purchase_request_id', $pr->id)->sole();

    expect($approval->step_name)->toBe(WorkflowStepName::BudgetOfficeEarmarking)
        ->and($approval->status)->toBe(WorkflowApprovalStatus::Pending)
        ->and($approval->approver_id)->toBe($budgetOfficer->id);

    Notification::assertSentTo($budgetOfficer, PurchaseRequestActionRequired::class);
    Notification::assertSentTo($this->dean, PurchaseRequestStatusUpdated::class);
});

test('activating still succeeds when no budget officer exists', function () {
    Notification::fake();

    $pr = PurchaseRequest::factory()->create([
        'department_id' => $this->department->id,
        'requester_id' => $this->dean->id,
        'status' => PurchaseRequestStatus::SupplyOfficeReview,
        'estimated_total' => 20,
    ]);

    $this->actingAs($this->supply)
        ->post(route('supply.purchase-requests.status', $pr), [
            'action' => 'activate',
        ])
        ->assertRedirect();

    expect($pr->refresh()->status)->toBe(PurchaseRequestStatus::BudgetOfficeReview)
        ->and(WorkflowApproval::query()->count())->toBe(0);

    Notification::assertNotSentTo($this->dean, PurchaseRequestActionRequired::class);
    Notification::assertSentTo($this->dean, PurchaseRequestStatusUpdated::class);
});

test('returning a request requires remarks', function () {
    $pr = PurchaseRequest::factory()->create([
        'department_id' => $this->department->id,
        'requester_id' => $this->dean->id,
        'status' => PurchaseRequestStatus::SupplyOfficeReview,
    ]);

    $this->actingAs($this->supply)
        ->post(route('supply.purchase-requests.status', $pr), [
            'action' => 'return',
        ])
        ->assertSessionHasErrors('remarks');
});

test('returning a request archives nothing and sets returned by supply', function () {
    Notification::fake();

    $pr = PurchaseRequest::factory()->create([
        'department_id' => $this->department->id,
        'requester_id' => $this->dean->id,
        'status' => PurchaseRequestStatus::SupplyOfficeReview,
        'estimated_total' => 20,
    ]);

    $this->actingAs($this->supply)
        ->post(route('supply.purchase-requests.status', $pr), [
            'action' => 'return',
            'remarks' => 'Specs are incomplete.',
        ])
        ->assertRedirect();

    $pr->refresh();

    expect($pr->status)->toBe(PurchaseRequestStatus::ReturnedBySupply)
        ->and($pr->return_remarks)->toBe('Specs are incomplete.')
        ->and($pr->is_archived)->toBeFalse();

    Notification::assertSentTo($this->dean, PurchaseRequestStatusUpdated::class);
});
