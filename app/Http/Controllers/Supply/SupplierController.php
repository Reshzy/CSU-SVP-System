<?php

namespace App\Http\Controllers\Supply;

use App\Concerns\FlashesToasts;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListQueryRequest;
use App\Http\Requests\Supply\StoreSupplierRequest;
use App\Http\Requests\Supply\UpdateSupplierRequest;
use App\Models\Supplier;
use App\Support\ListQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    use FlashesToasts;

    public function index(ListQueryRequest $request): Response
    {
        return Inertia::render('supply/suppliers/index', [
            'suppliers' => ListQuery::paginate(
                Supplier::query(),
                $request,
                [
                    'searchable' => ['business_name', 'supplier_code', 'contact_person'],
                    'sortable' => [
                        'supplier_code' => 'supplier_code',
                        'business_name' => 'business_name',
                        'status' => 'status',
                        'created_at' => 'created_at',
                    ],
                    'equals' => ['status' => 'status'],
                    'defaultSort' => 'created_at',
                    'defaultDirection' => 'desc',
                    'defaultPerPage' => 15,
                ],
            ),
            'filters' => ListQuery::snapshot($request, [
                'status' => $request->filled('status') ? $request->string('status')->value() : null,
            ]),
            'canApprove' => $request->user()?->hasRole('Executive Officer') ?? false,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('supply/suppliers/create');
    }

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        $supplier = DB::transaction(function () use ($request): Supplier {
            return Supplier::query()->create([
                ...$request->validated(),
                'supplier_code' => Supplier::generateSupplierCode(),
                'password' => null,
                'status' => 'pending_verification',
            ]);
        });

        $this->toast("Supplier {$supplier->business_name} registered. Pending verification.");

        return redirect()->route('supply.suppliers.create');
    }

    public function edit(Supplier $supplier): Response
    {
        return Inertia::render('supply/suppliers/edit', [
            'supplier' => $supplier,
        ]);
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->validated());

        $this->toast('Supplier updated.');

        return redirect()->route('supply.suppliers.index');
    }

    public function approve(Supplier $supplier): RedirectResponse
    {
        $supplier->update(['status' => 'active']);

        $this->toast('Supplier activated.');

        return back();
    }
}
