<?php

use App\Models\AoqGeneration;
use App\Models\Department;
use App\Models\DepartmentBudget;
use App\Models\DisbursementVoucher;
use App\Models\Document;
use App\Models\InventoryReceipt;
use App\Models\InventoryReceiptItem;
use App\Models\PrItemGroup;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->department = Department::factory()->create();

    $this->supplyOfficer = User::factory()->create();
    $this->supplyOfficer->assignRole('Supply Officer');

    $this->accountant = User::factory()->create();
    $this->accountant->assignRole('Accounting Office');
});

test('preview sends a single winner to the single po form and multiple winners to batch', function () {
    [$purchaseRequest, $itemA, $itemB] = readyUngroupedPr($this->department);
    $quoteA = winningQuote($purchaseRequest, $itemA);

    $this->actingAs($this->supplyOfficer)
        ->get(route('supply.purchase-orders.preview', $purchaseRequest))
        ->assertRedirect(route('supply.purchase-orders.create', $purchaseRequest));

    winningQuote($purchaseRequest, $itemB);

    $this->actingAs($this->supplyOfficer)
        ->get(route('supply.purchase-orders.preview', $purchaseRequest))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('supply/purchase-orders/preview')
            ->has('winners', 2));

    expect($quoteA->fresh()->is_winner)->toBeTrue();
});

test('batch store creates a pending approval po per winning supplier', function () {
    [$purchaseRequest, $itemA, $itemB] = readyUngroupedPr($this->department);
    $quoteA = winningQuote($purchaseRequest, $itemA, unitPrice: 90);
    $quoteB = winningQuote($purchaseRequest, $itemB, unitPrice: 350);
    $today = now()->toDateString();
    $prefix = 'PO-'.now()->format('my').'-';

    $this->actingAs($this->supplyOfficer)
        ->post(route('supply.purchase-orders.batch-store', $purchaseRequest), [
            'purchase_orders' => [
                batchPayload($quoteA, $itemA, $today, 90),
                batchPayload($quoteB, $itemB, $today, 350),
            ],
        ])
        ->assertRedirect(route('supply.purchase-orders.index'));

    $orders = PurchaseOrder::query()->orderBy('id')->get();

    expect($orders)->toHaveCount(2)
        ->and($orders->every(fn (PurchaseOrder $order): bool => $order->status === 'pending_approval'))->toBeTrue()
        ->and($orders[0]->po_number)->toBe($prefix.'0001')
        ->and($orders[1]->po_number)->toBe($prefix.'0002')
        ->and($orders[0]->supplier_id)->toBe($quoteA->quotation->supplier_id)
        ->and($orders[1]->supplier_id)->toBe($quoteB->quotation->supplier_id)
        ->and($purchaseRequest->fresh()->status)->toBe('po_generation');

    expect(PurchaseOrderItem::query()->count())->toBe(2)
        ->and(PurchaseOrderItem::query()->where('quotation_item_id', $quoteA->id)->exists())->toBeTrue()
        ->and(PurchaseOrderItem::query()->where('quotation_item_id', $quoteB->id)->exists())->toBeTrue();
});

test('completing a purchase order utilizes the reserved department budget', function () {
    DepartmentBudget::factory()->create([
        'department_id' => $this->department->id,
        'fiscal_year' => (int) now()->format('Y'),
        'allocated_budget' => 10_000,
        'reserved_budget' => 0,
        'utilized_budget' => 0,
    ]);

    $purchaseRequest = PurchaseRequest::factory()->create([
        'department_id' => $this->department->id,
        'status' => 'bac_evaluation',
        'estimated_total' => 200,
        'procurement_method' => 'small_value_procurement',
    ]);

    $item = PurchaseRequestItem::factory()->for($purchaseRequest)->create([
        'item_name' => 'Bond paper',
        'quantity_requested' => 2,
        'estimated_unit_cost' => 100,
        'estimated_total_cost' => 200,
        'parent_lot_id' => null,
        'is_lot' => false,
    ]);

    $quote = winningQuote($purchaseRequest, $item, unitPrice: 90);
    $today = now()->toDateString();

    $this->actingAs($this->supplyOfficer)
        ->post(route('supply.purchase-orders.store', $purchaseRequest), [
            'supplier_id' => $quote->quotation->supplier_id,
            'quotation_id' => $quote->quotation_id,
            'delivery_address' => 'CagSU Supply Office',
            'delivery_date_required' => $today,
            'terms_and_conditions' => 'Standard government procurement terms under RA 9184 apply.',
            'total_amount' => 180,
        ])
        ->assertRedirect();

    $purchaseOrder = PurchaseOrder::query()->first();
    $budget = DepartmentBudget::query()
        ->where('department_id', $this->department->id)
        ->where('fiscal_year', (int) now()->format('Y'))
        ->first();

    expect((float) $budget->reserved_budget)->toBe(200.0)
        ->and((float) $budget->utilized_budget)->toBe(0.0);

    $this->actingAs($this->supplyOfficer)
        ->put(route('supply.purchase-orders.update', $purchaseOrder), [
            'action' => 'complete',
        ])
        ->assertRedirect();

    $budget->refresh();
    $purchaseRequest->refresh();

    expect($purchaseOrder->fresh()->status)->toBe('completed')
        ->and($purchaseRequest->status)->toBe('completed')
        ->and((float) $budget->reserved_budget)->toBe(0.0)
        ->and((float) $budget->utilized_budget)->toBe(200.0);
});

test('a grouped pr can receive a po while another group stays in bac', function () {
    [$purchaseRequest, $itemA, $itemB] = readyUngroupedPr($this->department, 'bac_evaluation');
    $g1 = PrItemGroup::factory()->for($purchaseRequest)->create(['group_code' => 'G1', 'display_order' => 1]);
    $g2 = PrItemGroup::factory()->for($purchaseRequest)->create(['group_code' => 'G2', 'display_order' => 2]);
    $itemA->update(['pr_item_group_id' => $g1->id]);
    $itemB->update(['pr_item_group_id' => $g2->id]);

    winningQuote($purchaseRequest, $itemA, $g1->id, 90);
    winningQuote($purchaseRequest, $itemB, $g2->id, 350);
    seedAoq($purchaseRequest, $g1);
    seedAoq($purchaseRequest, $g2);

    expect($g1->fresh()->canCreatePo())->toBeTrue()
        ->and($g2->fresh()->canCreatePo())->toBeTrue();

    $this->actingAs($this->supplyOfficer)
        ->post(route('supply.purchase-orders.store', ['purchaseRequest' => $purchaseRequest, 'group' => $g1->id]), [
            'pr_item_group_id' => $g1->id,
            'supplier_id' => Quotation::query()->where('pr_item_group_id', $g1->id)->value('supplier_id'),
            'quotation_id' => Quotation::query()->where('pr_item_group_id', $g1->id)->value('id'),
            'delivery_address' => 'CagSU Supply Office',
            'delivery_date_required' => now()->toDateString(),
            'terms_and_conditions' => 'Standard government procurement terms under RA 9184 apply.',
            'total_amount' => 900,
        ])
        ->assertRedirect();

    $purchaseRequest->refresh();
    $purchaseRequest->unsetRelation('itemGroups');

    expect($g1->fresh()->computeStatus())->toBe('po_created')
        ->and($g2->fresh()->computeStatus())->toBe('aoq_generated')
        ->and($g2->fresh()->canCreatePo())->toBeTrue()
        ->and($purchaseRequest->status)->toBe('bac_approved')
        ->and(PurchaseOrder::query()->where('pr_item_group_id', $g1->id)->exists())->toBeTrue()
        ->and(PurchaseOrder::query()->where('pr_item_group_id', $g2->id)->exists())->toBeFalse();
});

test('complete can store an optional inspection report', function () {
    Storage::fake('public');

    [$purchaseRequest, $itemA] = readyUngroupedPr($this->department);
    $quote = winningQuote($purchaseRequest, $itemA);

    $this->actingAs($this->supplyOfficer)
        ->post(route('supply.purchase-orders.store', $purchaseRequest), [
            'supplier_id' => $quote->quotation->supplier_id,
            'quotation_id' => $quote->quotation_id,
            'delivery_address' => 'CagSU Supply Office',
            'delivery_date_required' => now()->toDateString(),
            'terms_and_conditions' => 'Standard government procurement terms under RA 9184 apply.',
        ])
        ->assertRedirect();

    $purchaseOrder = PurchaseOrder::query()->first();

    $this->actingAs($this->supplyOfficer)
        ->put(route('supply.purchase-orders.update', $purchaseOrder), [
            'action' => 'complete',
            'inspection_file' => UploadedFile::fake()->create('iar.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect();

    $document = Document::query()->where('document_type', 'inspection_report')->first();

    expect($document)->not->toBeNull()
        ->and($document->documentable_type)->toBe(PurchaseOrder::class)
        ->and($document->documentable_id)->toBe($purchaseOrder->id)
        ->and($document->document_number)->toMatch('/^DOC-\d{4}-\d{4}$/');
});

test('inventory receipt lines are free text and not linked to po items', function () {
    [$purchaseRequest, $itemA] = readyUngroupedPr($this->department);
    $quote = winningQuote($purchaseRequest, $itemA);

    $this->actingAs($this->supplyOfficer)
        ->post(route('supply.purchase-orders.store', $purchaseRequest), [
            'supplier_id' => $quote->quotation->supplier_id,
            'quotation_id' => $quote->quotation_id,
            'delivery_address' => 'CagSU Supply Office',
            'delivery_date_required' => now()->toDateString(),
            'terms_and_conditions' => 'Standard government procurement terms under RA 9184 apply.',
        ]);

    $purchaseOrder = PurchaseOrder::query()->first();

    $this->actingAs($this->supplyOfficer)
        ->post(route('supply.inventory-receipts.store', $purchaseOrder), [
            'received_date' => now()->toDateString(),
            'reference_no' => 'DR-1',
            'items' => [
                [
                    'description' => 'Reams received in bulk',
                    'unit_of_measure' => 'box',
                    'quantity' => 3,
                    'unit_price' => 50,
                ],
            ],
        ])
        ->assertRedirect();

    $receipt = InventoryReceipt::query()->first();
    $line = InventoryReceiptItem::query()->first();

    expect($receipt->status)->toBe('posted')
        ->and($line->description)->toBe('Reams received in bulk')
        ->and((float) $line->total_price)->toBe(150.0)
        ->and(Schema::hasColumn('inventory_receipt_items', 'purchase_order_item_id'))->toBeFalse();
});

test('disbursement vouchers use dv numbering and the accounting status chain', function () {
    [$purchaseRequest, $itemA] = readyUngroupedPr($this->department);
    $quote = winningQuote($purchaseRequest, $itemA);

    $this->actingAs($this->supplyOfficer)
        ->post(route('supply.purchase-orders.store', $purchaseRequest), [
            'supplier_id' => $quote->quotation->supplier_id,
            'quotation_id' => $quote->quotation_id,
            'delivery_address' => 'CagSU Supply Office',
            'delivery_date_required' => now()->toDateString(),
            'terms_and_conditions' => 'Standard government procurement terms under RA 9184 apply.',
            'total_amount' => 180,
        ]);

    $purchaseOrder = PurchaseOrder::query()->first();

    $this->actingAs($this->supplyOfficer)
        ->put(route('supply.purchase-orders.update', $purchaseOrder), ['action' => 'complete']);

    $this->actingAs($this->accountant)
        ->post(route('accounting.vouchers.store', $purchaseOrder), [
            'amount' => 180,
            'voucher_date' => now()->toDateString(),
        ])
        ->assertRedirect();

    $voucher = DisbursementVoucher::query()->first();
    $prefix = 'DV-'.now()->format('my').'-';

    expect($voucher->voucher_number)->toBe($prefix.'0001')
        ->and($voucher->status)->toBe('submitted');

    foreach (['approve' => 'approved', 'release' => 'released', 'mark_paid' => 'paid'] as $action => $status) {
        $this->actingAs($this->accountant)
            ->put(route('accounting.vouchers.update', $voucher), ['action' => $action])
            ->assertRedirect();

        expect($voucher->fresh()->status)->toBe($status);
    }
});

test('the purchase order list searches by po number and page size', function () {
    $matching = PurchaseOrder::factory()->create([
        'po_number' => 'PO-1026-0099',
        'status' => 'pending_approval',
        'created_at' => now()->subHour(),
    ]);
    PurchaseOrder::factory()->create([
        'po_number' => 'PO-1026-0100',
        'status' => 'approved',
        'created_at' => now(),
    ]);

    $this->actingAs($this->supplyOfficer)
        ->get(route('supply.purchase-orders.index', ['search' => '0099']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('supply/purchase-orders/index')
            ->count('purchaseOrders.data', 1)
            ->where('purchaseOrders.data.0.id', $matching->id)
        );

    $this->actingAs($this->supplyOfficer)
        ->get(route('supply.purchase-orders.index', ['per_page' => 10, 'sort' => 'status', 'direction' => 'asc']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('purchaseOrders.per_page', 10));
});

/**
 * @return array{0: PurchaseRequest, 1: PurchaseRequestItem, 2?: PurchaseRequestItem}
 */
function readyUngroupedPr(Department $department, string $status = 'bac_evaluation'): array
{
    $purchaseRequest = PurchaseRequest::factory()->create([
        'department_id' => $department->id,
        'status' => $status,
        'procurement_method' => 'small_value_procurement',
        'estimated_total' => 1800,
    ]);

    $itemA = PurchaseRequestItem::factory()->for($purchaseRequest)->create([
        'item_name' => 'Bond paper',
        'quantity_requested' => 10,
        'estimated_unit_cost' => 100,
        'estimated_total_cost' => 1000,
        'parent_lot_id' => null,
        'is_lot' => false,
    ]);

    $itemB = PurchaseRequestItem::factory()->for($purchaseRequest)->create([
        'item_name' => 'Laptop',
        'quantity_requested' => 2,
        'estimated_unit_cost' => 400,
        'estimated_total_cost' => 800,
        'parent_lot_id' => null,
        'is_lot' => false,
    ]);

    return [$purchaseRequest, $itemA, $itemB];
}

function winningQuote(
    PurchaseRequest $purchaseRequest,
    PurchaseRequestItem $item,
    ?int $groupId = null,
    float $unitPrice = 80,
): QuotationItem {
    $quotation = Quotation::factory()->for($purchaseRequest)->for(Supplier::factory())->create([
        'pr_item_group_id' => $groupId,
        'bac_status' => 'compliant',
    ]);

    return QuotationItem::factory()->for($quotation)->create([
        'purchase_request_item_id' => $item->id,
        'unit_price' => $unitPrice,
        'total_price' => $unitPrice * $item->quantity_requested,
        'is_winner' => true,
        'is_withdrawn' => false,
        'is_within_abc' => true,
    ]);
}

/**
 * @return array<string, mixed>
 */
function batchPayload(QuotationItem $quote, PurchaseRequestItem $item, string $today, float $unitPrice): array
{
    return [
        'supplier_id' => $quote->quotation->supplier_id,
        'quotation_id' => $quote->quotation_id,
        'delivery_address' => 'CagSU Supply Office',
        'delivery_date_required' => $today,
        'terms_and_conditions' => 'Standard government procurement terms under RA 9184 apply.',
        'total_amount' => $unitPrice * $item->quantity_requested,
        'items' => [
            [
                'purchase_request_item_id' => $item->id,
                'quotation_item_id' => $quote->id,
                'quantity' => $item->quantity_requested,
                'unit_price' => $unitPrice,
                'total_price' => $unitPrice * $item->quantity_requested,
            ],
        ],
    ];
}

function seedAoq(PurchaseRequest $purchaseRequest, PrItemGroup $group): void
{
    AoqGeneration::query()->create([
        'aoq_reference_number' => 'AOQ-'.now()->format('my').'-'.str_pad((string) $group->id, 4, '0', STR_PAD_LEFT),
        'purchase_request_id' => $purchaseRequest->id,
        'pr_item_group_id' => $group->id,
        'file_format' => 'docx',
    ]);
}
