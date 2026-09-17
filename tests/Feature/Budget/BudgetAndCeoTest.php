<?php

use App\Models\Department;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Models\WorkflowApproval;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->department = Department::factory()->create();
    $this->requester = User::factory()->create(['department_id' => $this->department->id]);
    $this->requester->assignRole('Dean');

    $this->budgetOfficer = User::factory()->create();
    $this->budgetOfficer->assignRole('Budget Office');

    $this->ceo = User::factory()->create();
    $this->ceo->assignRole('Executive Officer');

    $this->bacSecretary = User::factory()->create();
    $this->bacSecretary->assignRole('BAC Secretariat');

    $this->purchaseRequest = PurchaseRequest::factory()->create([
        'department_id' => $this->department->id,
        'requester_id' => $this->requester->id,
        'status' => 'budget_office_review',
        'estimated_total' => 500,
    ]);
});

test('earmark approve moves the purchase request to ceo_approval', function () {
    Notification::fake();

    $this->actingAs($this->budgetOfficer)
        ->put(route('budget.purchase-requests.update', $this->purchaseRequest), [
            'legal_basis' => 'Section 86 of RA 9184',
            'earmark_programs_activities' => 'Instruction',
            'earmark_responsibility_center' => 'CICS',
            'earmark_date_to' => now()->addMonth()->toDateString(),
            'earmark_object_expenditures' => [
                ['description' => 'Supplies', 'amount' => 500],
            ],
        ])
        ->assertRedirect();

    $this->purchaseRequest->refresh();

    expect($this->purchaseRequest->status)->toBe('ceo_approval')
        ->and($this->purchaseRequest->earmark_id)->toMatch('/^EM-\d{4}-\d{4}$/');

    expect(WorkflowApproval::query()
        ->where('purchase_request_id', $this->purchaseRequest->id)
        ->where('step_name', 'ceo_initial_approval')
        ->exists())->toBeTrue();
});

test('earmark reject defers the purchase request', function () {
    $this->actingAs($this->budgetOfficer)
        ->post(route('budget.purchase-requests.reject', $this->purchaseRequest), [
            'rejection_reason' => 'Insufficient justification',
        ])
        ->assertRedirect();

    expect($this->purchaseRequest->fresh()->status)->toBe('rejected');
});

test('earmark amend does not change workflow status', function () {
    $this->purchaseRequest->update([
        'status' => 'ceo_approval',
        'earmark_id' => 'EM-0926-0001',
        'legal_basis' => 'Old basis',
    ]);

    $this->actingAs($this->budgetOfficer)
        ->patch(route('budget.purchase-requests.amend-earmark', $this->purchaseRequest), [
            'legal_basis' => 'Amended basis',
            'earmark_programs_activities' => 'Instruction',
            'earmark_responsibility_center' => 'CICS',
            'earmark_date_to' => now()->addMonth()->toDateString(),
        ])
        ->assertRedirect();

    $this->purchaseRequest->refresh();

    expect($this->purchaseRequest->status)->toBe('ceo_approval')
        ->and($this->purchaseRequest->legal_basis)->toBe('Amended basis');
});

test('ceo approve sets bac_evaluation small_value_procurement and resolution number', function () {
    Notification::fake();
    Storage::fake('local');

    $this->purchaseRequest->update([
        'status' => 'ceo_approval',
        'earmark_id' => 'EM-0926-0001',
    ]);

    $this->actingAs($this->ceo)
        ->post(route('ceo.purchase-requests.update', $this->purchaseRequest), [
            'decision' => 'approve',
        ])
        ->assertRedirect();

    $this->purchaseRequest->refresh();

    expect($this->purchaseRequest->status)->toBe('bac_evaluation')
        ->and($this->purchaseRequest->procurement_method)->toBe('small_value_procurement')
        ->and($this->purchaseRequest->resolution_number)->toMatch('/^RES-\d{4}-\d{4}$/');

    expect(WorkflowApproval::query()
        ->where('purchase_request_id', $this->purchaseRequest->id)
        ->where('step_name', 'bac_evaluation')
        ->exists())->toBeTrue();
});

test('ceo reject defers the purchase request', function () {
    $this->purchaseRequest->update(['status' => 'ceo_approval']);

    $this->actingAs($this->ceo)
        ->post(route('ceo.purchase-requests.update', $this->purchaseRequest), [
            'decision' => 'reject',
            'rejection_reason' => 'Not aligned with priorities',
        ])
        ->assertRedirect();

    expect($this->purchaseRequest->fresh()->status)->toBe('rejected');
});
