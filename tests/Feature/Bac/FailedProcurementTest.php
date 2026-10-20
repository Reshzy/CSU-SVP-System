<?php

use App\Models\Department;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

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
        'is_archived' => false,
        'replaced_by_pr_id' => null,
    ]);

    $this->prItem = PurchaseRequestItem::factory()->for($this->purchaseRequest)->create([
        'item_name' => 'Bond paper',
        'quantity_requested' => 10,
        'estimated_unit_cost' => 100,
        'estimated_total_cost' => 1000,
        'parent_lot_id' => null,
        'is_lot' => false,
    ]);
});

test('marking an item failed is rejected while eligible bidders remain', function () {
    $quotation = Quotation::factory()->for($this->purchaseRequest)->for(Supplier::factory())->create();
    QuotationItem::factory()->for($quotation)->create([
        'purchase_request_item_id' => $this->prItem->id,
        'unit_price' => 90,
        'total_price' => 900,
        'is_within_abc' => true,
        'is_withdrawn' => false,
        'disqualification_reason' => null,
    ]);

    $this->actingAs($this->secretariat)
        ->from(route('bac.quotations.manage', $this->purchaseRequest))
        ->post(route('bac.pr-items.mark-failed', $this->prItem), [
            'failure_reason' => 'No remaining suppliers available.',
        ])
        ->assertRedirect(route('bac.quotations.manage', $this->purchaseRequest))
        ->assertSessionHasErrors('failure_reason');

    expect($this->prItem->fresh()->procurement_status)->not->toBe('failed');
});

test('failing a lot header cascades to lot children', function () {
    $header = PurchaseRequestItem::factory()->for($this->purchaseRequest)->create([
        'is_lot' => true,
        'lot_name' => 'Office lot',
        'parent_lot_id' => null,
        'unit_of_measure' => 'lot',
        'quantity_requested' => 1,
        'item_name' => 'Office lot',
    ]);

    $child = PurchaseRequestItem::factory()->for($this->purchaseRequest)->create([
        'is_lot' => false,
        'parent_lot_id' => $header->id,
        'item_name' => 'Stapler',
    ]);

    $this->actingAs($this->secretariat)
        ->post(route('bac.pr-items.mark-failed', $header), [
            'failure_reason' => 'No remaining suppliers available.',
        ])
        ->assertRedirect();

    expect($header->fresh()->procurement_status)->toBe('failed')
        ->and($child->fresh()->procurement_status)->toBe('failed');
});

test('a replacement pr is created without archiving the original', function () {
    $this->prItem->markAsFailed('All suppliers withdrew.');

    $this->actingAs($this->secretariat)
        ->post(route('bac.quotations.create-replacement-pr', $this->purchaseRequest))
        ->assertRedirect();

    $replacement = PurchaseRequest::query()->where('replaces_pr_id', $this->purchaseRequest->id)->first();

    expect($replacement)->not->toBeNull()
        ->and($replacement->status)->toBe('supply_office_review')
        ->and($this->purchaseRequest->fresh()->is_archived)->toBeFalse()
        ->and($this->purchaseRequest->fresh()->replaced_by_pr_id)->toBeNull()
        ->and($this->prItem->fresh()->procurement_status)->toBe('re_pr_created')
        ->and($this->prItem->fresh()->replacement_pr_id)->toBe($replacement->id);
});

test('a failed lot is copied onto the replacement pr as a lot with children', function () {
    $header = PurchaseRequestItem::factory()->for($this->purchaseRequest)->create([
        'is_lot' => true,
        'lot_name' => 'Office lot',
        'parent_lot_id' => null,
        'unit_of_measure' => 'lot',
        'quantity_requested' => 1,
        'estimated_unit_cost' => 500,
        'estimated_total_cost' => 500,
        'item_name' => 'Office lot',
    ]);

    $child = PurchaseRequestItem::factory()->for($this->purchaseRequest)->create([
        'is_lot' => false,
        'parent_lot_id' => $header->id,
        'item_name' => 'Stapler',
        'quantity_requested' => 2,
        'estimated_unit_cost' => 250,
        'estimated_total_cost' => 500,
    ]);

    $header->markAsFailed('All suppliers withdrew.');

    $this->actingAs($this->secretariat)
        ->post(route('bac.quotations.create-replacement-pr', $this->purchaseRequest))
        ->assertRedirect();

    $replacement = PurchaseRequest::query()->where('replaces_pr_id', $this->purchaseRequest->id)->first();
    $copiedHeader = $replacement->items()->whereNull('parent_lot_id')->first();
    $copiedChild = $replacement->items()->whereNotNull('parent_lot_id')->first();

    expect($replacement)->not->toBeNull()
        ->and($this->purchaseRequest->fresh()->is_archived)->toBeFalse()
        ->and($copiedHeader->is_lot)->toBeTrue()
        ->and($copiedHeader->lot_name)->toBe('Office lot')
        ->and($copiedChild->parent_lot_id)->toBe($copiedHeader->id)
        ->and($copiedChild->item_name)->toBe('Stapler')
        ->and($header->fresh()->replacement_pr_id)->toBe($replacement->id)
        ->and($child->fresh()->replacement_pr_id)->toBe($replacement->id)
        ->and($replacement->items()->count())->toBe(2);
});
