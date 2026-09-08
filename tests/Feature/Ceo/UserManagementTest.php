<?php

use App\Enums\ApprovalStatus;
use App\Models\Position;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->executiveOfficer = User::factory()->create();
    $this->executiveOfficer->assignRole('Executive Officer');
});

test('the executive officer sees pending registrations by default', function () {
    $pending = User::factory()->pending()->create();
    $approved = User::factory()->create();

    $this->actingAs($this->executiveOfficer)
        ->get(route('ceo.users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ceo/users/index')
            ->where('filters.status', ApprovalStatus::Pending->value)
            ->where('users.data.0.id', $pending->id)
            ->count('users.data', 1)
        );

    expect($approved->isApproved())->toBeTrue();
});

test('approving a user activates the account and records the approver', function () {
    $pending = User::factory()->pending()->create();

    $this->actingAs($this->executiveOfficer)
        ->post(route('ceo.users.approve', $pending))
        ->assertRedirect(route('ceo.users.index'));

    $pending->refresh();

    expect($pending->approval_status)->toBe(ApprovalStatus::Approved)
        ->and($pending->is_active)->toBeTrue()
        ->and($pending->approved_by)->toBe($this->executiveOfficer->id)
        ->and($pending->approved_at)->not->toBeNull();
});

test('approving a user assigns the role their position maps to', function () {
    $pending = User::factory()->pending()->create([
        'position_id' => Position::factory()->create(['name' => 'Supply Officer']),
    ]);

    $this->actingAs($this->executiveOfficer)
        ->post(route('ceo.users.approve', $pending));

    expect($pending->fresh()->getRoleNames()->all())->toEqual(['Supply Officer']);
});

test('approving a user with an unmapped position falls back to End User', function () {
    $pending = User::factory()->pending()->create([
        'position_id' => Position::factory()->create(['name' => 'Groundskeeper']),
    ]);

    $this->actingAs($this->executiveOfficer)
        ->post(route('ceo.users.approve', $pending));

    expect($pending->fresh()->getRoleNames()->all())->toEqual(['End User']);
});

test('approving a user does not overwrite a role they already hold', function () {
    $pending = User::factory()->pending()->create([
        'position_id' => Position::factory()->create(['name' => 'Employee']),
    ]);
    $pending->assignRole('Dean');

    $this->actingAs($this->executiveOfficer)
        ->post(route('ceo.users.approve', $pending));

    expect($pending->fresh()->getRoleNames()->all())->toEqual(['Dean']);
});

test('an approved user can then log in and reach the dashboard', function () {
    $pending = User::factory()->pending()->create();

    $this->actingAs($this->executiveOfficer)
        ->post(route('ceo.users.approve', $pending));

    $this->post(route('logout'));

    $this->post(route('login.store'), [
        'email' => $pending->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->get(route('dashboard'))->assertOk();
});

test('rejecting a user stores the reason and keeps them inactive', function () {
    $pending = User::factory()->pending()->create();

    $this->actingAs($this->executiveOfficer)
        ->post(route('ceo.users.reject', $pending), [
            'rejection_reason' => 'The uploaded ID does not match the given name.',
        ])
        ->assertRedirect(route('ceo.users.index'));

    $pending->refresh();

    expect($pending->approval_status)->toBe(ApprovalStatus::Rejected)
        ->and($pending->is_active)->toBeFalse()
        ->and($pending->rejection_reason)->toBe('The uploaded ID does not match the given name.')
        ->and($pending->rejected_by)->toBe($this->executiveOfficer->id)
        ->and($pending->rejected_at)->not->toBeNull();
});

test('rejecting a user requires a reason', function () {
    $pending = User::factory()->pending()->create();

    $this->actingAs($this->executiveOfficer)
        ->post(route('ceo.users.reject', $pending), ['rejection_reason' => ''])
        ->assertSessionHasErrors('rejection_reason');

    expect($pending->fresh()->approval_status)->toBe(ApprovalStatus::Pending);
});

test('a user without the Executive Officer role cannot reach the queue', function () {
    $dean = User::factory()->create();
    $dean->assignRole('Dean');

    $this->actingAs($dean)->get(route('ceo.users.index'))->assertForbidden();
});

test('a user without the Executive Officer role cannot approve anyone', function () {
    $dean = User::factory()->create();
    $dean->assignRole('Dean');
    $pending = User::factory()->pending()->create();

    $this->actingAs($dean)
        ->post(route('ceo.users.approve', $pending))
        ->assertForbidden();

    expect($pending->fresh()->approval_status)->toBe(ApprovalStatus::Pending);
});

test('guests are redirected away from the queue', function () {
    $this->get(route('ceo.users.index'))->assertRedirect(route('login'));
});
