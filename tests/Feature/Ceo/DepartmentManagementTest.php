<?php

use App\Models\Department;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->executiveOfficer = User::factory()->create();
    $this->executiveOfficer->assignRole('Executive Officer');
});

test('the executive officer can create a department', function () {
    $this->actingAs($this->executiveOfficer)
        ->post(route('ceo.departments.store'), [
            'name' => 'College of Marine Sciences',
            'code' => 'cms',
            'head_name' => 'Dr Reyes',
            'is_active' => '1',
            'is_archived' => '0',
        ])
        ->assertRedirect(route('ceo.departments.index'));

    $department = Department::where('name', 'College of Marine Sciences')->sole();

    expect($department->code)->toBe('CMS')
        ->and($department->head_name)->toBe('Dr Reyes')
        ->and($department->is_active)->toBeTrue()
        ->and($department->is_archived)->toBeFalse();
});

test('a department code cannot duplicate an existing one', function () {
    $existing = Department::factory()->create();

    $this->actingAs($this->executiveOfficer)
        ->post(route('ceo.departments.store'), [
            'name' => 'A different name',
            'code' => $existing->code,
        ])
        ->assertSessionHasErrors('code');
});

test('the executive officer can update a department', function () {
    $department = Department::factory()->create();

    $this->actingAs($this->executiveOfficer)
        ->put(route('ceo.departments.update', $department), [
            'name' => 'Renamed College',
            'code' => 'RC',
            'is_active' => '1',
            'is_archived' => '0',
        ])
        ->assertRedirect(route('ceo.departments.index'));

    expect($department->fresh()->name)->toBe('Renamed College')
        ->and($department->fresh()->code)->toBe('RC');
});

test('updating a department keeps its own code available', function () {
    $department = Department::factory()->create(['code' => 'CMS']);

    $this->actingAs($this->executiveOfficer)
        ->put(route('ceo.departments.update', $department), [
            'name' => $department->name,
            'code' => 'CMS',
        ])
        ->assertSessionHasNoErrors();
});

/**
 * Departments carry historical purchase requests, so archiving replaces
 * deletion; file 14 lists no destroy route.
 */
test('archiving a department removes it from the registration dropdown', function () {
    $department = Department::factory()->create();

    $this->actingAs($this->executiveOfficer)
        ->put(route('ceo.departments.update', $department), [
            'name' => $department->name,
            'code' => $department->code,
            'is_active' => '0',
            'is_archived' => '1',
        ]);

    expect($department->fresh()->is_archived)->toBeTrue()
        ->and(Department::selectable()->whereKey($department->id)->exists())->toBeFalse();
});

test('there is no route for deleting a department', function () {
    $department = Department::factory()->create();

    $this->actingAs($this->executiveOfficer)
        ->delete("/ceo/departments/{$department->id}")
        ->assertMethodNotAllowed();

    expect($department->fresh())->not->toBeNull();
});

test('the department list searches by name and can sort by code', function () {
    Department::factory()->create(['name' => 'College of Agriculture', 'code' => 'COA']);
    Department::factory()->create(['name' => 'College of Engineering', 'code' => 'COE']);

    $this->actingAs($this->executiveOfficer)
        ->get(route('ceo.departments.index', ['search' => 'Engineering']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ceo/departments/index')
            ->count('departments.data', 1)
            ->where('departments.data.0.code', 'COE')
        );

    $this->actingAs($this->executiveOfficer)
        ->get(route('ceo.departments.index', ['sort' => 'code', 'direction' => 'asc']))
        ->assertOk()
        ->assertInertia(function ($page) {
            $codes = collect($page->toArray()['props']['departments']['data'])->pluck('code')->all();
            $agriculture = array_search('COA', $codes, true);
            $engineering = array_search('COE', $codes, true);

            expect($agriculture)->toBeInt()
                ->and($engineering)->toBeInt()
                ->and($agriculture)->toBeLessThan($engineering);
        });
});

test('a user without the Executive Officer role cannot manage departments', function () {
    $dean = User::factory()->create();
    $dean->assignRole('Dean');

    $this->actingAs($dean)->get(route('ceo.departments.index'))->assertForbidden();
    $this->actingAs($dean)
        ->post(route('ceo.departments.store'), ['name' => 'Nope', 'code' => 'NOPE'])
        ->assertForbidden();

    expect(Department::where('code', 'NOPE')->exists())->toBeFalse();
});
