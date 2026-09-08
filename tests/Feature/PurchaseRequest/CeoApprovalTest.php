<?php

use App\Enums\PurchaseRequestStatus;
use App\Enums\WorkflowApprovalStatus;
use App\Enums\WorkflowStepName;
use App\Models\Department;
use App\Models\DepartmentBudget;
use App\Models\Document;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Models\WorkflowApproval;
use App\Notifications\PurchaseRequestActionRequired;
use App\Notifications\PurchaseRequestStatusUpdated;
use App\Services\BacResolutionService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->department = Department::factory()->create();
    $this->dean = User::factory()->create(['department_id' => $this->department->id]);
    $this->dean->assignRole('Dean');

    $this->ceo = User::factory()->create();
    $this->ceo->assignRole('Executive Officer');

    $this->secretariat = User::factory()->create();
    $this->secretariat->assignRole('BAC Secretariat');

    DepartmentBudget::factory()->for($this->department)->create([
        'fiscal_year' => now()->year,
        'allocated_budget' => 1_000_000,
    ]);
});

function ceoQueuePr(): PurchaseRequest
{
    return PurchaseRequest::factory()->create([
        'department_id' => test()->department->id,
        'requester_id' => test()->dean->id,
        'status' => PurchaseRequestStatus::CeoApproval,
        'estimated_total' => 20,
        'earmark_id' => PurchaseRequest::generateNextEarmarkId(),
        'legal_basis' => 'Section 86 of RA 9184',
    ]);
}

test('a dean cannot open the ceo queue', function () {
    $this->actingAs($this->dean)
        ->get(route('ceo.purchase-requests.index'))
        ->assertForbidden();
});

test('ceo approve sets small value procurement and mints a resolution number', function () {
    Notification::fake();

    $pr = ceoQueuePr();

    $this->actingAs($this->ceo)
        ->post(route('ceo.purchase-requests.update', $pr), [
            'decision' => 'approve',
        ])
        ->assertRedirect(route('ceo.purchase-requests.show', $pr));

    $pr->refresh();

    expect($pr->status)->toBe(PurchaseRequestStatus::BacEvaluation)
        ->and($pr->procurement_method)->toBe('small_value_procurement')
        ->and($pr->procurement_method_set_by)->toBe($this->ceo->id)
        ->and($pr->resolution_number)->toStartWith('RES-');

    $approval = WorkflowApproval::query()->where('purchase_request_id', $pr->id)->sole();

    expect($approval->step_name)->toBe(WorkflowStepName::BacEvaluation)
        ->and($approval->status)->toBe(WorkflowApprovalStatus::Pending)
        ->and($approval->approver_id)->toBe($this->secretariat->id);

    expect(Document::query()->where('document_type', 'bac_resolution')->count())->toBe(1);

    Notification::assertSentTo($this->secretariat, PurchaseRequestActionRequired::class);
    Notification::assertSentTo($this->dean, PurchaseRequestStatusUpdated::class);
});

test('ceo approve still succeeds when resolution generation fails', function () {
    Notification::fake();

    $this->mock(BacResolutionService::class, function ($mock): void {
        $mock->shouldReceive('generateResolution')->once()->andThrow(new RuntimeException('docx failed'));
    });

    $pr = ceoQueuePr();

    $this->actingAs($this->ceo)
        ->post(route('ceo.purchase-requests.update', $pr), [
            'decision' => 'approve',
        ])
        ->assertRedirect();

    $pr->refresh();

    expect($pr->status)->toBe(PurchaseRequestStatus::BacEvaluation)
        ->and($pr->procurement_method)->toBe('small_value_procurement')
        ->and($pr->resolution_number)->toStartWith('RES-');
});

test('ceo reject defers the request', function () {
    Notification::fake();

    $pr = ceoQueuePr();

    $this->actingAs($this->ceo)
        ->post(route('ceo.purchase-requests.update', $pr), [
            'decision' => 'reject',
            'rejection_reason' => 'Not aligned with priority.',
        ])
        ->assertRedirect();

    $pr->refresh();

    expect($pr->status)->toBe(PurchaseRequestStatus::Rejected)
        ->and($pr->rejection_reason)->toBe('Not aligned with priority.');

    Notification::assertSentTo($this->dean, PurchaseRequestStatusUpdated::class);
});
