<?php

use App\Models\Position;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('approved users get the role their position maps to', function (string $position, string $role) {
    $user = User::factory()->create([
        'position_id' => Position::factory()->create(['name' => $position]),
    ]);

    $this->artisan('users:assign-roles')->assertSuccessful();

    expect($user->fresh()->getRoleNames()->all())->toEqual([$role]);
})->with([
    ['System Administrator', 'System Admin'],
    ['Supply Officer', 'Supply Officer'],
    ['Budget Officer', 'Budget Office'],
    ['Executive Officer', 'Executive Officer'],
    ['BAC Chairman', 'BAC Chair'],
    ['BAC Member', 'BAC Members'],
    ['BAC Secretary', 'BAC Secretariat'],
    ['Accounting Officer', 'Accounting Office'],
    ['Canvassing Officer', 'Canvassing Unit'],
    ['Dean', 'Dean'],
    ['Employee', 'End User'],
]);

test('an unknown position falls back to End User', function () {
    $user = User::factory()->create([
        'position_id' => Position::factory()->create(['name' => 'Groundskeeper']),
    ]);

    $this->artisan('users:assign-roles')->assertSuccessful();

    expect($user->fresh()->getRoleNames()->all())->toEqual(['End User']);
});

test('a user with no position falls back to End User', function () {
    $user = User::factory()->create(['position_id' => null]);

    $this->artisan('users:assign-roles')->assertSuccessful();

    expect($user->fresh()->getRoleNames()->all())->toEqual(['End User']);
});

test('users who already hold a role are skipped', function () {
    $user = User::factory()->create([
        'position_id' => Position::factory()->create(['name' => 'Employee']),
    ]);
    $user->assignRole('Supply Officer');

    $this->artisan('users:assign-roles')->assertSuccessful();

    expect($user->fresh()->getRoleNames()->all())->toEqual(['Supply Officer']);
});

test('users who are not approved are left alone', function () {
    $pending = User::factory()->pending()->create([
        'position_id' => Position::factory()->create(['name' => 'Dean']),
    ]);

    $this->artisan('users:assign-roles')->assertSuccessful();

    expect($pending->fresh()->getRoleNames())->toBeEmpty();
});
