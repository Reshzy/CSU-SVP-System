<?php

use App\Models\Ppmp;
use App\Models\PpmpItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;

beforeEach(function () {
    $this->ppmp = Ppmp::factory()->create();

    $this->item = PpmpItem::factory()->for($this->ppmp)->create([
        'q1_quantity' => 10,
        'q2_quantity' => 10,
        'q3_quantity' => 10,
        'q4_quantity' => 10,
        'total_quantity' => 40,
    ]);

    $this->request = function (string $status = 'supply_office_review', bool $archived = false): PurchaseRequest {
        return PurchaseRequest::factory()->create([
            'status' => $status,
            'is_archived' => $archived,
        ]);
    };
});

test('a plan line starts with its whole planned quantity remaining', function () {
    expect($this->item->getRemainingQuantity())->toBe(40)
        ->and($this->item->getRemainingQuantity(1))->toBe(10);
});

test('a live purchase request consumes the quantity it asked for', function () {
    PurchaseRequestItem::factory()->for(($this->request)())->for($this->item, 'ppmpItem')->create([
        'ppmp_quarter' => 1,
        'quantity_requested' => 4,
    ]);

    expect($this->item->getRemainingQuantity())->toBe(36)
        ->and($this->item->getRemainingQuantity(1))->toBe(6);
});

test('a request against one quarter leaves the other quarters untouched', function () {
    PurchaseRequestItem::factory()->for(($this->request)())->for($this->item, 'ppmpItem')->create([
        'ppmp_quarter' => 1,
        'quantity_requested' => 4,
    ]);

    expect($this->item->getRemainingQuantity(2))->toBe(10);
});

test('a rejected request releases the quantity it held', function () {
    PurchaseRequestItem::factory()->for(($this->request)('rejected'))->for($this->item, 'ppmpItem')->create([
        'ppmp_quarter' => 1,
        'quantity_requested' => 4,
    ]);

    expect($this->item->getRemainingQuantity())->toBe(40);
});

test('a cancelled request releases the quantity it held', function () {
    PurchaseRequestItem::factory()->for(($this->request)('cancelled'))->for($this->item, 'ppmpItem')->create([
        'ppmp_quarter' => 1,
        'quantity_requested' => 4,
    ]);

    expect($this->item->getRemainingQuantity())->toBe(40);
});

test('an archived request releases the quantity it held', function () {
    PurchaseRequestItem::factory()
        ->for(($this->request)('supply_office_review', archived: true))
        ->for($this->item, 'ppmpItem')
        ->create(['ppmp_quarter' => 1, 'quantity_requested' => 4]);

    expect($this->item->getRemainingQuantity())->toBe(40);
});

test('a returned request still consumes quantity until it is archived', function () {
    $returned = ($this->request)('returned_by_supply');

    PurchaseRequestItem::factory()->for($returned)->for($this->item, 'ppmpItem')->create([
        'ppmp_quarter' => 1,
        'quantity_requested' => 4,
    ]);

    expect($this->item->getRemainingQuantity())->toBe(36);

    $returned->update(['is_archived' => true]);

    expect($this->item->fresh()->getRemainingQuantity())->toBe(40);
});

test('one request can be excluded so an edit does not count against itself', function () {
    $own = ($this->request)();

    PurchaseRequestItem::factory()->for($own)->for($this->item, 'ppmpItem')->create([
        'ppmp_quarter' => 1,
        'quantity_requested' => 4,
    ]);

    PurchaseRequestItem::factory()->for(($this->request)())->for($this->item, 'ppmpItem')->create([
        'ppmp_quarter' => 1,
        'quantity_requested' => 3,
    ]);

    expect($this->item->getRemainingQuantity(1))->toBe(3)
        ->and($this->item->getRemainingQuantity(1, $own->id))->toBe(7);
});

test('remaining quantity never reports a negative figure', function () {
    PurchaseRequestItem::factory()->for(($this->request)())->for($this->item, 'ppmpItem')->create([
        'ppmp_quarter' => 1,
        'quantity_requested' => 99,
    ]);

    expect($this->item->getRemainingQuantity(1))->toBe(0);
});

test('quantity on a different plan line does not bleed across', function () {
    $other = PpmpItem::factory()->for($this->ppmp)->create([
        'q1_quantity' => 10,
        'total_quantity' => 10,
    ]);

    PurchaseRequestItem::factory()->for(($this->request)())->for($other, 'ppmpItem')->create([
        'ppmp_quarter' => 1,
        'quantity_requested' => 6,
    ]);

    expect($this->item->getRemainingQuantity(1))->toBe(10)
        ->and($other->getRemainingQuantity(1))->toBe(4);
});
