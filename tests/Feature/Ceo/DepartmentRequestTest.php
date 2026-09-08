<?php

use App\Enums\ApprovalStatus;
use App\Models\Department;
use App\Models\DepartmentRequest;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->executiveOfficer = User::factory()->create();
    $this->executiveOfficer->assignRole('Executive Officer');
});

test('a guest can request a department that is missing from the dropdown', function () {
    $this->get(route('register.request-department'))->assertOk();

    $this->post(route('register.request-department.store'), [
        'name' => 'College of Marine Sciences',
        'code' => 'cms',
        'requester_email' => 'applicant@cagsu.edu.ph',
    ])->assertRedirect(route('register'));

    $request = DepartmentRequest::sole();

    expect($request->name)->toBe('College of Marine Sciences')
        ->and($request->code)->toBe('CMS')
        ->and($request->status)->toBe(ApprovalStatus::Pending);
});

test('a department request cannot duplicate an existing department', function () {
    $existing = Department::factory()->create();

    $this->post(route('register.request-department.store'), [
        'name' => $existing->name,
        'code' => $existing->code,
        'requester_email' => 'applicant@cagsu.edu.ph',
    ])->assertSessionHasErrors(['name', 'code']);

    expect(DepartmentRequest::count())->toBe(0)
        ->and($existing->fresh())->not->toBeNull();
});

test('a department request cannot duplicate another pending request', function () {
    $pending = DepartmentRequest::factory()->create();

    $this->post(route('register.request-department.store'), [
        'name' => $pending->name,
        'code' => $pending->code,
        'requester_email' => 'applicant@cagsu.edu.ph',
    ])->assertSessionHasErrors(['name', 'code']);

    expect(DepartmentRequest::count())->toBe(1);
});

test('approving a request creates the department and it becomes selectable', function () {
    $request = DepartmentRequest::factory()->create([
        'name' => 'College of Marine Sciences',
        'code' => 'CMS',
        'head_name' => 'Dr Reyes',
    ]);

    $this->actingAs($this->executiveOfficer)
        ->post(route('ceo.department-requests.approve', $request))
        ->assertRedirect(route('ceo.department-requests.index'));

    $request->refresh();
    $department = Department::where('code', 'CMS')->sole();

    expect($request->status)->toBe(ApprovalStatus::Approved)
        ->and($request->reviewed_by)->toBe($this->executiveOfficer->id)
        ->and($request->reviewed_at)->not->toBeNull()
        ->and($department->name)->toBe('College of Marine Sciences')
        ->and($department->head_name)->toBe('Dr Reyes')
        ->and($department->is_active)->toBeTrue();

    $this->post(route('logout'));

    $this->get(route('register'))
        ->assertInertia(fn ($page) => $page->where(
            'departments',
            fn ($departments) => collect($departments)->contains('code', 'CMS'),
        ));
});

test('rejecting a request stores the reason and creates no department', function () {
    $request = DepartmentRequest::factory()->create();

    $this->actingAs($this->executiveOfficer)
        ->post(route('ceo.department-requests.reject', $request), [
            'rejection_reason' => 'Duplicate of an existing college.',
        ])
        ->assertRedirect(route('ceo.department-requests.index'));

    $request->refresh();

    expect($request->status)->toBe(ApprovalStatus::Rejected)
        ->and($request->rejection_reason)->toBe('Duplicate of an existing college.')
        ->and($request->reviewed_by)->toBe($this->executiveOfficer->id)
        ->and(Department::where('code', $request->code)->exists())->toBeFalse();
});

test('rejecting a request requires a reason', function () {
    $request = DepartmentRequest::factory()->create();

    $this->actingAs($this->executiveOfficer)
        ->post(route('ceo.department-requests.reject', $request), ['rejection_reason' => ''])
        ->assertSessionHasErrors('rejection_reason');

    expect($request->fresh()->status)->toBe(ApprovalStatus::Pending);
});

test('an already reviewed request is not approved twice', function () {
    $request = DepartmentRequest::factory()->rejected()->create();

    $this->actingAs($this->executiveOfficer)
        ->post(route('ceo.department-requests.approve', $request));

    expect($request->fresh()->status)->toBe(ApprovalStatus::Rejected)
        ->and(Department::where('code', $request->code)->exists())->toBeFalse();
});

/**
 * `departments` is unique on both name and code, so a department created by
 * hand while the request waited must not blow up the approval.
 */
test('approving a request that now collides is a validation error', function () {
    $request = DepartmentRequest::factory()->create(['code' => 'CMS']);
    Department::factory()->create(['code' => 'CMS']);

    $this->actingAs($this->executiveOfficer)
        ->post(route('ceo.department-requests.approve', $request))
        ->assertSessionHasErrors('code');

    expect($request->fresh()->status)->toBe(ApprovalStatus::Pending)
        ->and(Department::where('code', 'CMS')->count())->toBe(1);
});

test('a user without the Executive Officer role cannot review requests', function () {
    $dean = User::factory()->create();
    $dean->assignRole('Dean');
    $request = DepartmentRequest::factory()->create();

    $this->actingAs($dean)
        ->post(route('ceo.department-requests.approve', $request))
        ->assertForbidden();

    expect($request->fresh()->status)->toBe(ApprovalStatus::Pending);
});
