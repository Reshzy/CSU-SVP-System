<?php

namespace App\Http\Controllers\Supply;

use App\Concerns\FlashesToasts;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListQueryRequest;
use App\Http\Requests\Supply\StoreBatchPurchaseOrderRequest;
use App\Http\Requests\Supply\StorePurchaseOrderRequest;
use App\Models\Document;
use App\Models\PoSignatory;
use App\Models\PrItemGroup;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Services\PurchaseOrderExportService;
use App\Services\PurchaseOrderService;
use App\Support\ListQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PurchaseOrderController extends Controller
{
    use FlashesToasts;

    public function __construct(private PurchaseOrderService $purchaseOrderService) {}

    public function index(ListQueryRequest $request): Response
    {
        $query = PurchaseOrder::query()
            ->with([
                'supplier:id,business_name',
                'purchaseRequest:id,pr_number,pr_title,status',
            ]);

        $statusOptions = (clone $query)
            ->reorder()
            ->select('status')
            ->distinct()
            ->orderBy('status')
            ->pluck('status')
            ->map(fn (string $status): array => [
                'value' => $status,
                'label' => str_replace('_', ' ', $status),
            ])
            ->values()
            ->all();

        $purchaseOrders = ListQuery::paginate($query, $request, [
            'searchable' => ['po_number', 'purchaseRequest.pr_number', 'supplier.business_name'],
            'sortable' => [
                'po_number' => 'po_number',
                'status' => 'status',
                'total_amount' => 'total_amount',
                'created_at' => 'created_at',
            ],
            'equals' => ['status' => 'status'],
            'dateColumn' => 'created_at',
            'defaultSort' => 'created_at',
            'defaultDirection' => 'desc',
            'defaultPerPage' => 20,
        ]);

        $readyPurchaseRequests = PurchaseRequest::query()
            ->with(['department:id,name,code', 'itemGroups.purchaseOrders', 'itemGroups.aoqGeneration'])
            ->where('is_archived', false)
            ->whereIn('status', ['bac_evaluation', 'bac_approved', 'partial_po_generation', 'po_generation'])
            ->latest()
            ->get()
            ->filter(fn (PurchaseRequest $purchaseRequest): bool => $purchaseRequest->canCreatePo())
            ->values()
            ->map(fn (PurchaseRequest $purchaseRequest): array => [
                'id' => $purchaseRequest->id,
                'pr_number' => $purchaseRequest->pr_number,
                'pr_title' => $purchaseRequest->pr_title,
                'status' => $purchaseRequest->status,
                'department' => $purchaseRequest->department,
            ]);

        return Inertia::render('supply/purchase-orders/index', [
            'purchaseOrders' => $purchaseOrders,
            'readyPurchaseRequests' => $readyPurchaseRequests,
            'filters' => ListQuery::snapshot($request, [
                'status' => $request->filled('status') ? $request->string('status')->value() : null,
            ]),
            'statusOptions' => $statusOptions,
        ]);
    }

    public function preview(Request $request, PurchaseRequest $purchaseRequest): Response|RedirectResponse
    {
        abort_unless($purchaseRequest->canCreatePo(), 403);

        $itemGroup = $this->resolveItemGroup($request, $purchaseRequest);
        $winners = $this->purchaseOrderService->getWinningItemsGroupedBySupplier($purchaseRequest, $itemGroup);

        if ($winners->count() === 1) {
            return redirect()->route('supply.purchase-orders.create', [
                'purchaseRequest' => $purchaseRequest,
                'group' => $itemGroup?->id,
            ]);
        }

        return Inertia::render('supply/purchase-orders/preview', [
            'purchaseRequest' => $this->purchaseRequestPayload($purchaseRequest),
            'itemGroup' => $itemGroup,
            'winners' => $this->winnerPayload($winners),
        ]);
    }

    public function create(Request $request, PurchaseRequest $purchaseRequest): Response
    {
        abort_unless($purchaseRequest->canCreatePo(), 403);

        $itemGroup = $this->resolveItemGroup($request, $purchaseRequest);
        $winners = $this->purchaseOrderService->getWinningItemsGroupedBySupplier($purchaseRequest, $itemGroup);
        $winningSupplier = $winners->first();

        return Inertia::render('supply/purchase-orders/create', [
            'purchaseRequest' => $this->purchaseRequestPayload($purchaseRequest),
            'itemGroup' => $itemGroup,
            'winningSupplier' => $winningSupplier ? $this->winnerPayload(collect([$winningSupplier]))[0] : null,
            'nextPoNumber' => PurchaseOrder::generateNextPoNumber(),
            'ceoSignatory' => PoSignatory::query()->active()->position('ceo')->with('user:id,name')->first(),
            'chiefAccountantSignatory' => PoSignatory::query()->active()->position('chief_accountant')->with('user:id,name')->first(),
            'defaultTerms' => 'Standard government procurement terms under RA 9184 apply.',
        ]);
    }

    public function store(StorePurchaseOrderRequest $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        abort_unless($purchaseRequest->canCreatePo(), 403);

        $validated = $request->validated();
        $itemGroup = $this->resolveItemGroup($request, $purchaseRequest);

        $items = $this->purchaseOrderService->itemsPayloadForSupplier(
            $purchaseRequest,
            (int) $validated['supplier_id'],
            $itemGroup,
        );

        abort_unless($items !== [], 422, 'No winning items found for this supplier.');

        if (! isset($validated['quotation_id'])) {
            $winners = $this->purchaseOrderService->getWinningItemsGroupedBySupplier($purchaseRequest, $itemGroup);
            $match = $winners->first(fn (array $row): bool => (int) $row['supplier']->id === (int) $validated['supplier_id']);
            $validated['quotation_id'] = $match['quotation']->id ?? null;
            $validated['total_amount'] ??= $match['total_amount'] ?? null;
        }

        $validated['items'] = $items;

        $purchaseOrder = $this->purchaseOrderService->createPurchaseOrder($purchaseRequest, $itemGroup, $validated);

        $this->toast('Purchase order created.');

        return redirect()->route('supply.purchase-orders.show', $purchaseOrder);
    }

    public function batchCreate(Request $request, PurchaseRequest $purchaseRequest): Response
    {
        abort_unless($purchaseRequest->canCreatePo(), 403);

        $itemGroup = $this->resolveItemGroup($request, $purchaseRequest);
        $winners = $this->purchaseOrderService->getWinningItemsGroupedBySupplier($purchaseRequest, $itemGroup);

        return Inertia::render('supply/purchase-orders/batch-create', [
            'purchaseRequest' => $this->purchaseRequestPayload($purchaseRequest),
            'itemGroup' => $itemGroup,
            'winners' => $this->winnerPayload($winners),
            'ceoSignatory' => PoSignatory::query()->active()->position('ceo')->with('user:id,name')->first(),
            'chiefAccountantSignatory' => PoSignatory::query()->active()->position('chief_accountant')->with('user:id,name')->first(),
            'defaultTerms' => 'Standard government procurement terms under RA 9184 apply.',
        ]);
    }

    public function batchStore(StoreBatchPurchaseOrderRequest $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        abort_unless($purchaseRequest->canCreatePo(), 403);

        $validated = $request->validated();
        $itemGroup = isset($validated['pr_item_group_id'])
            ? PrItemGroup::query()->find($validated['pr_item_group_id'])
            : $this->resolveItemGroup($request, $purchaseRequest);

        if ($itemGroup) {
            abort_unless($itemGroup->purchase_request_id === $purchaseRequest->id && $itemGroup->canCreatePo(), 403);
        }

        $this->purchaseOrderService->createBatchPurchaseOrders(
            $purchaseRequest,
            $itemGroup,
            $validated['purchase_orders'],
        );

        $this->toast(count($validated['purchase_orders']).' purchase orders created.');

        return redirect()->route('supply.purchase-orders.index');
    }

    public function show(PurchaseOrder $purchaseOrder): Response
    {
        $purchaseOrder->load([
            'purchaseRequest:id,pr_number,pr_title,status,department_id',
            'supplier:id,business_name,address,tin',
            'items.purchaseRequestItem',
            'documents',
        ]);

        return Inertia::render('supply/purchase-orders/show', [
            'purchaseOrder' => $purchaseOrder,
        ]);
    }

    public function edit(PurchaseOrder $purchaseOrder): Response
    {
        $purchaseOrder->load(['purchaseRequest', 'supplier', 'quotation', 'itemGroup']);

        return Inertia::render('supply/purchase-orders/edit', [
            'purchaseOrder' => $purchaseOrder,
        ]);
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:send_to_supplier,acknowledge,mark_delivered,complete,edit'],
            'notes' => ['nullable', 'string'],
            'inspection_file' => ['nullable', 'file', 'max:10240'],
            'supplier_id' => ['required_if:action,edit', 'exists:suppliers,id'],
            'tin' => ['nullable', 'string', 'max:50'],
            'supplier_name_override' => ['nullable', 'string', 'max:255'],
            'funds_cluster' => ['nullable', 'string'],
            'funds_available' => ['nullable', 'numeric', 'min:0'],
            'ors_burs_no' => ['nullable', 'string'],
            'ors_burs_date' => ['nullable', 'date'],
            'total_amount' => ['nullable', 'numeric', 'min:0'],
            'delivery_address' => ['required_if:action,edit', 'string'],
            'delivery_date_required' => ['required_if:action,edit', 'date'],
            'terms_and_conditions' => ['required_if:action,edit', 'string'],
            'special_instructions' => ['nullable', 'string'],
        ]);

        if ($validated['action'] === 'edit') {
            $purchaseOrder->update([
                'supplier_id' => $validated['supplier_id'],
                'tin' => $validated['tin'] ?? null,
                'supplier_name_override' => $validated['supplier_name_override'] ?? null,
                'funds_cluster' => filled($validated['funds_cluster'] ?? null) ? $validated['funds_cluster'] : null,
                'funds_available' => isset($validated['funds_available']) && $validated['funds_available'] !== '' ? $validated['funds_available'] : null,
                'ors_burs_no' => filled($validated['ors_burs_no'] ?? null) ? $validated['ors_burs_no'] : null,
                'ors_burs_date' => isset($validated['ors_burs_date']) && $validated['ors_burs_date'] !== '' ? $validated['ors_burs_date'] : null,
                'total_amount' => isset($validated['total_amount']) && $validated['total_amount'] !== '' ? $validated['total_amount'] : null,
                'delivery_address' => $validated['delivery_address'],
                'delivery_date_required' => $validated['delivery_date_required'],
                'terms_and_conditions' => $validated['terms_and_conditions'],
                'special_instructions' => $validated['special_instructions'] ?? null,
            ]);

            $this->toast('Purchase order updated.');

            return redirect()->route('supply.purchase-orders.show', $purchaseOrder);
        }

        match ($validated['action']) {
            'send_to_supplier' => $purchaseOrder->fill([
                'status' => 'sent_to_supplier',
                'sent_to_supplier_at' => now(),
            ]),
            'acknowledge' => $purchaseOrder->fill([
                'status' => 'acknowledged_by_supplier',
                'acknowledged_at' => now(),
            ]),
            'mark_delivered' => $purchaseOrder->fill([
                'status' => 'delivered',
                'actual_delivery_date' => now()->toDateString(),
            ]),
            'complete' => $purchaseOrder->fill([
                'status' => 'completed',
                'delivery_complete' => true,
            ]),
            default => null,
        };

        $purchaseOrder->save();

        $purchaseRequest = $purchaseOrder->purchaseRequest;

        if ($purchaseRequest) {
            $purchaseRequest->unsetRelation('itemGroups');
            $purchaseRequest->load('itemGroups.purchaseOrders');

            if ($purchaseRequest->itemGroups->isNotEmpty()) {
                $purchaseRequest->syncStatusFromGroups();
            } else {
                $newStatus = match ($validated['action']) {
                    'send_to_supplier', 'acknowledge' => 'supplier_processing',
                    'mark_delivered' => 'delivered',
                    'complete' => 'completed',
                    default => $purchaseRequest->status,
                };

                if ($purchaseRequest->status !== $newStatus) {
                    $purchaseRequest->status = $newStatus;
                    $purchaseRequest->status_updated_at = now();

                    if ($newStatus === 'completed') {
                        $purchaseRequest->completed_at = now();
                    }

                    $purchaseRequest->save();
                }
            }
        }

        if ($request->hasFile('inspection_file')) {
            $file = $request->file('inspection_file');
            $path = $file->store('documents', 'public');

            $purchaseOrder->documents()->create([
                'document_number' => Document::generateNextDocumentNumber(),
                'document_type' => 'inspection_report',
                'title' => 'Inspection & Acceptance Report',
                'description' => 'Uploaded upon completion',
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getClientMimeType(),
                'uploaded_by' => $request->user()?->id,
                'is_public' => false,
                'status' => 'approved',
            ]);
        }

        $this->toast('Purchase order updated.');

        return back();
    }

    public function export(PurchaseOrder $purchaseOrder, PurchaseOrderExportService $exportService): BinaryFileResponse|RedirectResponse
    {
        try {
            return $exportService->download($purchaseOrder);
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withErrors(['export' => 'Unable to export this purchase order.']);
        }
    }

    private function resolveItemGroup(Request $request, PurchaseRequest $purchaseRequest): ?PrItemGroup
    {
        $groupId = $request->query('group') ?? $request->input('pr_item_group_id');

        if (! $groupId) {
            return null;
        }

        $itemGroup = PrItemGroup::query()->find($groupId);

        if ($itemGroup === null || $itemGroup->purchase_request_id !== $purchaseRequest->id) {
            abort(404);
        }

        if (in_array($request->route()?->getActionMethod(), ['create', 'store', 'preview', 'batchCreate', 'batchStore'], true)) {
            abort_unless($itemGroup->canCreatePo(), 403, 'This group cannot have more purchase orders created.');
        }

        return $itemGroup;
    }

    /**
     * @return array<string, mixed>
     */
    private function purchaseRequestPayload(PurchaseRequest $purchaseRequest): array
    {
        return [
            'id' => $purchaseRequest->id,
            'pr_number' => $purchaseRequest->pr_number,
            'pr_title' => $purchaseRequest->pr_title,
            'status' => $purchaseRequest->status,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $winners
     * @return list<array<string, mixed>>
     */
    private function winnerPayload($winners): array
    {
        return $winners->map(function (array $row): array {
            return [
                'supplier' => [
                    'id' => $row['supplier']->id,
                    'business_name' => $row['supplier']->business_name,
                    'address' => $row['supplier']->address,
                    'tin' => $row['supplier']->tin,
                ],
                'quotation' => [
                    'id' => $row['quotation']->id,
                    'quotation_number' => $row['quotation']->quotation_number,
                ],
                'total_amount' => $row['total_amount'],
                'item_count' => $row['item_count'],
                'items' => $row['items']->map(fn (array $item): array => [
                    'purchase_request_item_id' => $item['pr_item']->id,
                    'quotation_item_id' => $item['quotation_item']->id,
                    'item_name' => $item['pr_item']->item_name,
                    'unit_of_measure' => $item['pr_item']->unit_of_measure,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['total_price'],
                ])->values()->all(),
            ];
        })->all();
    }
}
