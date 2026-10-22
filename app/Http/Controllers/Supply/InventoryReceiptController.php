<?php

namespace App\Http\Controllers\Supply;

use App\Concerns\FlashesToasts;
use App\Http\Controllers\Controller;
use App\Models\InventoryReceipt;
use App\Models\InventoryReceiptItem;
use App\Models\PurchaseOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class InventoryReceiptController extends Controller
{
    use FlashesToasts;

    public function index(): Response
    {
        $receipts = InventoryReceipt::query()
            ->with(['purchaseOrder:id,po_number,status', 'receivedBy:id,name'])
            ->latest('received_date')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('supply/inventory-receipts/index', [
            'receipts' => $receipts,
        ]);
    }

    public function create(PurchaseOrder $purchaseOrder): Response
    {
        $purchaseOrder->load(['supplier:id,business_name', 'items.purchaseRequestItem']);

        return Inertia::render('supply/inventory-receipts/create', [
            'purchaseOrder' => $purchaseOrder,
        ]);
    }

    public function store(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $validated = $request->validate([
            'received_date' => ['required', 'date'],
            'reference_no' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string'],
            'items.*.unit_of_measure' => ['nullable', 'string', 'max:50'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $receipt = DB::transaction(function () use ($validated, $purchaseOrder, $request): InventoryReceipt {
            $receipt = InventoryReceipt::query()->create([
                'purchase_order_id' => $purchaseOrder->id,
                'received_date' => $validated['received_date'],
                'reference_no' => $validated['reference_no'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'status' => 'posted',
                'received_by' => $request->user()?->id,
            ]);

            foreach ($validated['items'] as $item) {
                $unitPrice = $item['unit_price'] ?? null;

                InventoryReceiptItem::query()->create([
                    'inventory_receipt_id' => $receipt->id,
                    'description' => $item['description'],
                    'unit_of_measure' => $item['unit_of_measure'] ?? null,
                    'quantity' => $item['quantity'],
                    'unit_price' => $unitPrice,
                    'total_price' => $unitPrice !== null ? (float) $unitPrice * (float) $item['quantity'] : null,
                ]);
            }

            return $receipt;
        });

        $this->toast('Inventory receipt recorded.');

        return redirect()->route('supply.inventory-receipts.show', $receipt);
    }

    public function show(InventoryReceipt $inventoryReceipt): Response
    {
        $inventoryReceipt->load(['purchaseOrder.supplier', 'items', 'receivedBy:id,name']);

        return Inertia::render('supply/inventory-receipts/show', [
            'receipt' => $inventoryReceipt,
        ]);
    }
}
