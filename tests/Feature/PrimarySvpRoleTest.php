<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('a single role is returned as the primary role', function (string $role) {
    $user = User::factory()->create();
    $user->assignRole($role);

    expect($user->getPrimarySVPRole())->toBe($role);
})->with(RolePermissionSeeder::ROLES);

test('a user with no role falls back to End User', function () {
    expect(User::factory()->create()->getPrimarySVPRole())->toBe('End User');
});

/**
 * The seniority order in file 12 is what decides which dashboard a user lands
 * on when they hold more than one role.
 */
test('the most senior role wins when a user holds several', function (array $roles, string $expected) {
    $user = User::factory()->create();
    $user->assignRole($roles);

    expect($user->getPrimarySVPRole())->toBe($expected);
})->with([
    'System Admin outranks everything' => [['Dean', 'Supply Officer', 'System Admin'], 'System Admin'],
    'Executive Officer outranks Supply Officer' => [['Supply Officer', 'Executive Officer'], 'Executive Officer'],
    'Supply Officer outranks BAC Chair' => [['BAC Chair', 'Supply Officer'], 'Supply Officer'],
    'BAC Chair outranks Budget Office' => [['Budget Office', 'BAC Chair'], 'BAC Chair'],
    'Budget Office outranks BAC Members' => [['BAC Members', 'Budget Office'], 'Budget Office'],
    'BAC Members outranks BAC Secretariat' => [['BAC Secretariat', 'BAC Members'], 'BAC Members'],
    'BAC Secretariat outranks Canvassing Unit' => [['Canvassing Unit', 'BAC Secretariat'], 'BAC Secretariat'],
    'Canvassing Unit outranks Accounting Office' => [['Accounting Office', 'Canvassing Unit'], 'Canvassing Unit'],
    'Accounting Office outranks Dean' => [['Dean', 'Accounting Office'], 'Accounting Office'],
    'Dean outranks End User' => [['End User', 'Dean'], 'Dean'],
    'End User outranks Supplier' => [['Supplier', 'End User'], 'End User'],
]);

test('the primary role is shared with the front end', function () {
    $user = User::factory()->create();
    $user->assignRole('Supply Officer');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('auth.primaryRole', 'Supply Officer')
            ->where('auth.roles', ['Supply Officer'])
        );
});

/**
 * System Admin passes every gate but is an operator account, not a requester.
 * `StorePurchaseRequestRequest::authorize()` will call this and deliberately
 * override `PurchaseRequestPolicy::create()`.
 */
test('System Admin cannot create purchase requests', function () {
    $user = User::factory()->create();
    $user->assignRole('System Admin');

    expect($user->canCreatePurchaseRequests())->toBeFalse()
        ->and($user->can('create-purchase-request'))->toBeTrue();
});

test('requester roles can create purchase requests', function (string $role) {
    $user = User::factory()->create();
    $user->assignRole($role);

    expect($user->canCreatePurchaseRequests())->toBeTrue();
})->with(['Dean', 'End User', 'Executive Officer']);
