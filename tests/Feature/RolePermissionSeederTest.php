<?php

use App\Models\Department;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('all 48 permissions are seeded with their exact names', function () {
    expect(Permission::pluck('name')->sort()->values()->all())
        ->toEqual(collect(RolePermissionSeeder::permissions())->sort()->values()->all())
        ->and(Permission::count())->toBe(48);
});

test('all 12 roles are seeded with their exact names', function () {
    expect(Role::pluck('name')->sort()->values()->all())
        ->toEqual(collect(RolePermissionSeeder::ROLES)->sort()->values()->all())
        ->and(Role::count())->toBe(12);
});

test('System Admin and Executive Officer hold every permission', function (string $role) {
    expect(Role::findByName($role)->permissions)->toHaveCount(48);
})->with(['System Admin', 'Executive Officer']);

test('a Dean can create purchase requests and view reports', function () {
    $dean = User::factory()->create();
    $dean->assignRole('Dean');

    expect($dean->can('create-purchase-request'))->toBeTrue()
        ->and($dean->can('view-reports'))->toBeTrue()
        ->and($dean->can('view-budget-info'))->toBeTrue();
});

test('an End User cannot view reports', function () {
    $endUser = User::factory()->create();
    $endUser->assignRole('End User');

    expect($endUser->can('create-purchase-request'))->toBeTrue()
        ->and($endUser->can('view-reports'))->toBeFalse();
});

test('the Supply Officer is the role that can edit purchase requests', function () {
    $supplyOfficer = User::factory()->create();
    $supplyOfficer->assignRole('Supply Officer');

    expect($supplyOfficer->can('edit-purchase-request'))->toBeTrue();
});

test('the Supplier role exists even though no portal is routed', function () {
    expect(Role::findByName('Supplier')->permissions->pluck('name')->sort()->values()->all())
        ->toEqual([
            'track-delivery',
            'upload-documents',
            'view-documents',
            'view-purchase-request',
            'view-supplier-info',
        ]);
});

/**
 * The gate is what keeps CEO and admin screens from failing on a permission
 * nobody thought to grant, so prove it with an ability that is not a seeded
 * permission at all.
 */
test('the super admin gate passes abilities that were never granted', function (string $role) {
    $user = User::factory()->create();
    $user->assignRole($role);

    expect(Gate::forUser($user)->allows('an-ability-that-does-not-exist'))->toBeTrue();
})->with(['System Admin', 'Executive Officer']);

test('the super admin gate does not leak to other roles', function () {
    $dean = User::factory()->create();
    $dean->assignRole('Dean');

    expect(Gate::forUser($dean)->allows('an-ability-that-does-not-exist'))->toBeFalse()
        ->and($dean->can('system-configuration'))->toBeFalse();
});

test('the seeder creates the Administrative Office department', function () {
    $admin = Department::where('code', 'ADMIN')->sole();

    expect($admin->name)->toBe('Administrative Office')
        ->and($admin->is_active)->toBeTrue();
});

test('reseeding does not duplicate roles or permissions', function () {
    $this->seed(RolePermissionSeeder::class);

    expect(Role::count())->toBe(12)
        ->and(Permission::count())->toBe(48)
        ->and(Department::where('code', 'ADMIN')->count())->toBe(1);
});
