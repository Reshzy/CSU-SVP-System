<?php

namespace App\Http\Controllers\Accounting;

use App\Concerns\FlashesToasts;
use App\Http\Controllers\Controller;
use App\Models\DisbursementVoucher;
use App\Models\PurchaseOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DisbursementVoucherController extends Controller
{
    use FlashesToasts;

    public function index(): Response
    {
        $vouchers = DisbursementVoucher::query()
            ->with(['purchaseOrder:id,po_number,status', 'supplier:id,business_name'])
            ->latest('voucher_date')
            ->paginate(15)
            ->withQueryString();

        $payableOrders = PurchaseOrder::query()
            ->with('supplier:id,business_name')
            ->whereIn('status', ['delivered', 'completed'])
            ->latest()
            ->get(['id', 'po_number', 'status', 'total_amount', 'supplier_id']);

        return Inertia::render('accounting/vouchers/index', [
            'vouchers' => $vouchers,
            'payableOrders' => $payableOrders,
        ]);
    }

    public function create(PurchaseOrder $purchaseOrder): Response
    {
        abort_unless(in_array($purchaseOrder->status, ['delivered', 'completed'], true), 403);

        $purchaseOrder->load(['supplier:id,business_name,tin', 'purchaseRequest:id,pr_number,pr_title']);

        return Inertia::render('accounting/vouchers/create', [
            'purchaseOrder' => $purchaseOrder,
        ]);
    }

    public function store(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_unless(in_array($purchaseOrder->status, ['delivered', 'completed'], true), 403);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'voucher_date' => ['required', 'date'],
            'remarks' => ['nullable', 'string'],
        ]);

        $voucher = DB::transaction(function () use ($validated, $purchaseOrder, $request): DisbursementVoucher {
            return DisbursementVoucher::query()->create([
                'voucher_number' => DisbursementVoucher::generateNextVoucherNumber(),
                'purchase_order_id' => $purchaseOrder->id,
                'supplier_id' => $purchaseOrder->supplier_id,
                'amount' => $validated['amount'],
                'voucher_date' => $validated['voucher_date'],
                'status' => 'submitted',
                'prepared_by' => $request->user()?->id,
                'remarks' => $validated['remarks'] ?? null,
            ]);
        });

        $this->toast('Disbursement voucher created.');

        return redirect()->route('accounting.vouchers.show', $voucher);
    }

    public function show(DisbursementVoucher $voucher): Response
    {
        $voucher->load([
            'purchaseOrder:id,po_number,status,total_amount,purchase_request_id',
            'purchaseOrder.purchaseRequest:id,pr_number,pr_title',
            'supplier:id,business_name,tin',
            'preparedBy:id,name',
            'approvedBy:id,name',
        ]);

        return Inertia::render('accounting/vouchers/show', [
            'voucher' => $voucher,
        ]);
    }

    public function update(Request $request, DisbursementVoucher $voucher): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:approve,release,mark_paid,cancel'],
            'remarks' => ['nullable', 'string'],
        ]);

        match ($validated['action']) {
            'approve' => $voucher->fill([
                'status' => 'approved',
                'approved_by' => $request->user()?->id,
                'approved_at' => now(),
            ]),
            'release' => $voucher->fill([
                'status' => 'released',
                'released_at' => now(),
            ]),
            'mark_paid' => $voucher->fill([
                'status' => 'paid',
                'paid_at' => now(),
            ]),
            'cancel' => $voucher->fill([
                'status' => 'cancelled',
            ]),
            default => null,
        };

        if (filled($validated['remarks'] ?? null)) {
            $voucher->remarks = trim((string) $validated['remarks']);
        }

        $voucher->save();
        $this->toast('Voucher updated.');

        return back();
    }
}
