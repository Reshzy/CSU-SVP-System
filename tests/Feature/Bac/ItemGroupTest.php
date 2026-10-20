<?php

use App\Models\Department;
use App\Models\PrItemGroup;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Quotation;
use App\Models\RfqGeneration;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->department = Department::factory()->create();
    $this->secretariat = User::factory()->create();
    $this->secretariat->assignRole('BAC Secretariat');

    $this->purchaseRequest = PurchaseRequest::factory()->create([
        'department_id' => $this->department->id,
        'status' => 'bac_evaluation',
        'procurement_method' => 'small_value_procurement',
        'resolution_number' => 'RES-0926-0001',
    ]);

    $this->itemA = PurchaseRequestItem::factory()->for($this->purchaseRequest)->create([
        'item_name' => 'Bond paper',
        'quantity_requested' => 10,
        'estimated_unit_cost' => 100,
        'estimated_total_cost' => 1000,
        'parent_lot_id' => null,
        'is_lot' => false,
    ]);

    $this->itemB = PurchaseRequestItem::factory()->for($this->purchaseRequest)->create([
        'item_name' => 'Laptop',
        'quantity_requested' => 2,
        'estimated_unit_cost' => 400,
        'estimated_total_cost' => 800,
        'parent_lot_id' => null,
        'is_lot' => false,
    ]);
});

test('bac secretariat can split a pr into g1 and g2 groups', function () {
    $this->actingAs($this->secretariat)
        ->post(route('bac.item-groups.store', $this->purchaseRequest), [
            'groups' => [
                ['name' => 'Office supplies', 'items' => [$this->itemA->id]],
                ['name' => 'IT equipment', 'items' => [$this->itemB->id]],
            ],
        ])
        ->assertRedirect(route('bac.quotations.manage', $this->purchaseRequest));

    $groups = $this->purchaseRequest->itemGroups()->orderBy('display_order')->get();

    expect($groups)->toHaveCount(2)
        ->and($groups->pluck('group_code')->all())->toBe(['G1', 'G2'])
        ->and($this->itemA->fresh()->pr_item_group_id)->toBe($groups[0]->id)
        ->and($this->itemB->fresh()->pr_item_group_id)->toBe($groups[1]->id);
});

test('the same supplier can quote each group while duplicates in one group are rejected', function () {
    $this->actingAs($this->secretariat)
        ->post(route('bac.item-groups.store', $this->purchaseRequest), [
            'groups' => [
                ['name' => 'Office supplies', 'items' => [$this->itemA->id]],
                ['name' => 'IT equipment', 'items' => [$this->itemB->id]],
            ],
        ]);

    $g1 = PrItemGroup::query()->where('purchase_request_id', $this->purchaseRequest->id)->where('group_code', 'G1')->first();
    $g2 = PrItemGroup::query()->where('purchase_request_id', $this->purchaseRequest->id)->where('group_code', 'G2')->first();
    $supplier = Supplier::factory()->create();

    $this->actingAs($this->secretariat)
        ->post(route('bac.quotations.store', $this->purchaseRequest), [
            'supplier_id' => $supplier->id,
            'pr_item_group_id' => $g1->id,
            'items' => [
                ['purchase_request_item_id' => $this->itemA->id, 'unit_price' => 90],
            ],
        ])
        ->assertRedirect();

    $this->actingAs($this->secretariat)
        ->post(route('bac.quotations.store', $this->purchaseRequest), [
            'supplier_id' => $supplier->id,
            'pr_item_group_id' => $g2->id,
            'items' => [
                ['purchase_request_item_id' => $this->itemB->id, 'unit_price' => 350],
            ],
        ])
        ->assertRedirect();

    expect(Quotation::query()->where('supplier_id', $supplier->id)->count())->toBe(2);

    $this->actingAs($this->secretariat)
        ->from(route('bac.quotations.manage', $this->purchaseRequest))
        ->post(route('bac.quotations.store', $this->purchaseRequest), [
            'supplier_id' => $supplier->id,
            'pr_item_group_id' => $g1->id,
            'items' => [
                ['purchase_request_item_id' => $this->itemA->id, 'unit_price' => 85],
            ],
        ])
        ->assertRedirect(route('bac.quotations.manage', $this->purchaseRequest))
        ->assertSessionHasErrors('supplier_id');
});

test('item groups cannot be managed outside bac evaluation', function () {
    $this->purchaseRequest->update(['status' => 'submitted']);

    $this->actingAs($this->secretariat)
        ->post(route('bac.item-groups.store', $this->purchaseRequest), [
            'groups' => [
                ['name' => 'Office supplies', 'items' => [$this->itemA->id]],
            ],
        ])
        ->assertForbidden();
});

test('group save is rejected when a quotable item is left unassigned', function () {
    $this->actingAs($this->secretariat)
        ->from(route('bac.item-groups.create', $this->purchaseRequest))
        ->post(route('bac.item-groups.store', $this->purchaseRequest), [
            'groups' => [
                ['name' => 'Office supplies', 'items' => [$this->itemA->id]],
            ],
        ])
        ->assertRedirect(route('bac.item-groups.create', $this->purchaseRequest))
        ->assertSessionHasErrors('groups');

    expect($this->purchaseRequest->itemGroups()->count())->toBe(0);
});

test('a user without a bac role cannot manage item groups', function () {
    $dean = User::factory()->create();
    $dean->assignRole('Dean');

    $this->actingAs($dean)
        ->post(route('bac.item-groups.store', $this->purchaseRequest), [
            'groups' => [
                ['name' => 'Office supplies', 'items' => [$this->itemA->id]],
                ['name' => 'IT equipment', 'items' => [$this->itemB->id]],
            ],
        ])
        ->assertForbidden();
});

test('item groups cannot be edited or destroyed after a quotation exists', function () {
    $this->actingAs($this->secretariat)
        ->post(route('bac.item-groups.store', $this->purchaseRequest), [
            'groups' => [
                ['name' => 'Office supplies', 'items' => [$this->itemA->id]],
                ['name' => 'IT equipment', 'items' => [$this->itemB->id]],
            ],
        ]);

    $g1 = PrItemGroup::query()->where('purchase_request_id', $this->purchaseRequest->id)->where('group_code', 'G1')->first();

    $this->actingAs($this->secretariat)
        ->post(route('bac.quotations.store', $this->purchaseRequest), [
            'supplier_id' => Supplier::factory()->create()->id,
            'pr_item_group_id' => $g1->id,
            'items' => [
                ['purchase_request_item_id' => $this->itemA->id, 'unit_price' => 90],
            ],
        ]);

    $payload = [
        'groups' => [
            ['name' => 'Office supplies', 'items' => [$this->itemA->id]],
            ['name' => 'IT equipment', 'items' => [$this->itemB->id]],
        ],
    ];

    $this->actingAs($this->secretariat)
        ->get(route('bac.item-groups.edit', $this->purchaseRequest))
        ->assertForbidden();

    $this->actingAs($this->secretariat)
        ->put(route('bac.item-groups.update', $this->purchaseRequest), $payload)
        ->assertForbidden();

    $this->actingAs($this->secretariat)
        ->delete(route('bac.item-groups.destroy', $this->purchaseRequest))
        ->assertForbidden();

    expect($this->purchaseRequest->itemGroups()->count())->toBe(2);
});

test('generating an rfq for a group does not create one for the other group', function () {
    Storage::fake('local');

    $this->actingAs($this->secretariat)
        ->post(route('bac.item-groups.store', $this->purchaseRequest), [
            'groups' => [
                ['name' => 'Office supplies', 'items' => [$this->itemA->id]],
                ['name' => 'IT equipment', 'items' => [$this->itemB->id]],
            ],
        ]);

    $g1 = PrItemGroup::query()->where('purchase_request_id', $this->purchaseRequest->id)->where('group_code', 'G1')->first();
    $g2 = PrItemGroup::query()->where('purchase_request_id', $this->purchaseRequest->id)->where('group_code', 'G2')->first();

    $this->actingAs($this->secretariat)
        ->post(route('bac.item-groups.rfq.generate', $g1))
        ->assertRedirect();

    expect(RfqGeneration::query()->where('pr_item_group_id', $g1->id)->count())->toBe(1)
        ->and(RfqGeneration::query()->where('pr_item_group_id', $g2->id)->count())->toBe(0);
});
