<?php

use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->supplyOfficer = User::factory()->create();
    $this->supplyOfficer->assignRole('Supply Officer');

    $this->canvassing = User::factory()->create();
    $this->canvassing->assignRole('Canvassing Unit');

    $this->executive = User::factory()->create();
    $this->executive->assignRole('Executive Officer');

    $this->endUser = User::factory()->create();
    $this->endUser->assignRole('End User');
});

test('the canvassing unit can register an internal supplier as pending', function () {
    $this->actingAs($this->canvassing)
        ->post(route('supply.suppliers.store'), [
            'business_name' => 'North Star Trading',
            'contact_person' => 'Ana Cruz',
            'email' => 'northstar@supplier.local',
            'phone' => '078-111-0001',
            'address' => 'Sanchez Mira, Cagayan',
            'business_type' => 'corporation',
        ])
        ->assertRedirect(route('supply.suppliers.create'));

    $supplier = Supplier::query()->where('email', 'northstar@supplier.local')->sole();

    expect($supplier->status)->toBe('pending_verification')
        ->and($supplier->password)->toBeNull()
        ->and($supplier->supplier_code)->toMatch('/^SUP-\d{4}-\d{4}$/');
});

test('the supply officer can update an internal supplier', function () {
    $supplier = Supplier::factory()->create([
        'status' => 'pending_verification',
        'email' => 'me@supplier.local',
    ]);

    $this->actingAs($this->supplyOfficer)
        ->put(route('supply.suppliers.update', $supplier), [
            'business_name' => $supplier->business_name,
            'contact_person' => 'Updated contact',
            'email' => $supplier->email,
            'phone' => '078-222-0002',
            'address' => 'Centro, Sanchez Mira',
            'business_type' => 'partnership',
            'status' => 'inactive',
        ])
        ->assertRedirect(route('supply.suppliers.index'));

    expect($supplier->fresh()->status)->toBe('inactive')
        ->and($supplier->fresh()->contact_person)->toBe('Updated contact');
});

test('the executive officer can approve a pending supplier', function () {
    $supplier = Supplier::factory()->create(['status' => 'pending_verification']);

    $this->actingAs($this->executive)
        ->from(route('supply.suppliers.index'))
        ->post(route('supply.suppliers.approve', $supplier))
        ->assertRedirect(route('supply.suppliers.index'));

    expect($supplier->fresh()->status)->toBe('active');
});

test('an end user cannot manage suppliers', function () {
    $this->actingAs($this->endUser)
        ->get(route('supply.suppliers.index'))
        ->assertForbidden();
});

test('public supplier portal urls stay unavailable', function () {
    $this->get('/suppliers/register')->assertNotFound();
    $this->post('/suppliers/register')->assertNotFound();
    $this->get('/suppliers/quotations/submit')->assertNotFound();
    $this->get('/suppliers/po-status')->assertNotFound();
    $this->get('/suppliers/contact')->assertNotFound();
});

test('the supplier index searches by business name', function () {
    Supplier::factory()->create(['business_name' => 'Lienmavel']);
    Supplier::factory()->create(['business_name' => 'Pandayan']);

    $this->actingAs($this->supplyOfficer)
        ->get(route('supply.suppliers.index', ['search' => 'Lien']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('supply/suppliers/index')
            ->count('suppliers.data', 1)
            ->where('suppliers.data.0.business_name', 'Lienmavel')
        );
});
