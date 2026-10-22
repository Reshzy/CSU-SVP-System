<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportFilterRequest;
use App\Models\Department;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Quotation;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController extends Controller
{
    /**
     * @var array<string, string>
     */
    public const CUSTOM_COLUMNS = [
        'pr_number' => 'PR Number',
        'created_at' => 'Created At',
        'requester' => 'Requester',
        'department' => 'Department',
        'purpose' => 'Purpose',
        'date_needed' => 'Date Needed',
        'priority' => 'Priority',
        'estimated_total' => 'Estimated Total',
        'status' => 'Status',
    ];

    /**
     * @var list<string>
     */
    public const PR_STATUSES = [
        'draft',
        'submitted',
        'supply_office_review',
        'budget_office_review',
        'ceo_approval',
        'bac_evaluation',
        'bac_approved',
        'partial_po_generation',
        'po_generation',
        'po_approved',
        'supplier_processing',
        'delivered',
        'completed',
        'cancelled',
        'rejected',
        'returned_by_supply',
    ];

    public function pr(ReportFilterRequest $request): InertiaResponse
    {
        $filters = $this->filtersFrom($request);

        return Inertia::render('reports/pr', [
            'purchaseRequests' => $this->purchaseRequestQuery($request)
                ->latest()
                ->paginate(20)
                ->withQueryString(),
            'filters' => $filters,
            'departments' => $this->departments(),
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    public function prExport(ReportFilterRequest $request): StreamedResponse
    {
        $query = $this->purchaseRequestQuery($request)->orderBy('created_at');
        $filename = 'purchase_requests_report_'.now()->format('Ymd_His').'.csv';

        return $this->streamCsv($filename, function ($handle) use ($query): void {
            fputcsv($handle, [
                'PR Number',
                'Created At',
                'Requester',
                'Department',
                'Purpose',
                'Date Needed',
                'Priority',
                'Estimated Total',
                'Status',
            ]);

            $query->chunk(500, function (Collection $chunk) use ($handle): void {
                foreach ($chunk as $purchaseRequest) {
                    fputcsv($handle, [
                        $purchaseRequest->pr_number,
                        optional($purchaseRequest->created_at)->format('Y-m-d H:i'),
                        $purchaseRequest->requester?->name,
                        $purchaseRequest->department?->name,
                        $purchaseRequest->purpose,
                        optional($purchaseRequest->date_needed)->format('Y-m-d'),
                        '',
                        number_format((float) $purchaseRequest->estimated_total, 2, '.', ''),
                        $purchaseRequest->status,
                    ]);
                }
            });
        });
    }

    public function analytics(): InertiaResponse
    {
        $labels = collect(range(0, 11))->map(function (int $i): string {
            return now()->startOfMonth()->subMonths(11 - $i)->format('Y-m');
        });

        $counts = $labels->map(function (string $yearMonth): int {
            [$year, $month] = array_map('intval', explode('-', $yearMonth));

            return PurchaseRequest::query()
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->count();
        });

        $cycle = $labels->map(function (string $yearMonth): ?float {
            [$year, $month] = array_map('intval', explode('-', $yearMonth));

            $rows = PurchaseRequest::query()
                ->whereYear('completed_at', $year)
                ->whereMonth('completed_at', $month)
                ->whereNotNull('submitted_at')
                ->whereNotNull('completed_at')
                ->get(['submitted_at', 'completed_at']);

            if ($rows->isEmpty()) {
                return null;
            }

            return round((float) $rows->avg(
                fn (PurchaseRequest $purchaseRequest): float => $purchaseRequest->submitted_at->diffInDays($purchaseRequest->completed_at),
            ), 1);
        });

        return Inertia::render('reports/analytics', [
            'labels' => $labels->values()->all(),
            'counts' => $counts->values()->all(),
            'cycle' => $cycle->values()->all(),
        ]);
    }

    public function suppliers(): InertiaResponse
    {
        return Inertia::render('reports/suppliers', [
            'rows' => $this->supplierPerformanceRows(),
        ]);
    }

    public function suppliersExport(): StreamedResponse
    {
        $rows = $this->supplierPerformanceRows();
        $filename = 'supplier_performance_'.now()->format('Ymd_His').'.csv';

        return $this->streamCsv($filename, function ($handle) use ($rows): void {
            fputcsv($handle, [
                'Supplier',
                'Quotes',
                'Awards',
                'Win Rate %',
                'Awarded Value',
                'POs',
                'Completed POs',
                'PO Value',
            ]);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['supplier']['business_name'],
                    $row['total_quotes'],
                    $row['awards'],
                    $row['win_rate'],
                    number_format((float) $row['awarded_total'], 2, '.', ''),
                    $row['po_count'],
                    $row['po_completed'],
                    number_format((float) $row['po_total'], 2, '.', ''),
                ]);
            }
        });
    }

    public function budget(): InertiaResponse
    {
        [$rows, $totals] = $this->budgetUtilization();

        return Inertia::render('reports/budget', [
            'rows' => $rows,
            'totals' => $totals,
        ]);
    }

    public function budgetExport(): StreamedResponse
    {
        [$rows, $totals] = $this->budgetUtilization();
        $filename = 'budget_utilization_'.now()->format('Ymd_His').'.csv';

        return $this->streamCsv($filename, function ($handle) use ($rows, $totals): void {
            fputcsv($handle, [
                'Department',
                'PR Count',
                'PR Total',
                'PO Count',
                'PO Total',
                'Utilization %',
            ]);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['department']['name'],
                    $row['pr_count'],
                    number_format((float) $row['pr_total'], 2, '.', ''),
                    $row['po_count'],
                    number_format((float) $row['po_total'], 2, '.', ''),
                    $row['utilization_rate'],
                ]);
            }

            fputcsv($handle, []);
            fputcsv($handle, [
                'Totals',
                '',
                number_format((float) $totals['pr_total'], 2, '.', ''),
                '',
                number_format((float) $totals['po_total'], 2, '.', ''),
                '',
            ]);
        });
    }

    public function custom(ReportFilterRequest $request): InertiaResponse
    {
        $selected = $this->selectedColumns($request);

        return Inertia::render('reports/custom', [
            'purchaseRequests' => $this->purchaseRequestQuery($request)
                ->latest()
                ->paginate(25)
                ->withQueryString(),
            'filters' => $this->filtersFrom($request),
            'available' => self::CUSTOM_COLUMNS,
            'selected' => $selected,
            'departments' => $this->departments(),
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    public function customExport(ReportFilterRequest $request): StreamedResponse
    {
        $selected = $this->selectedColumns($request);
        $query = $this->purchaseRequestQuery($request)->latest();
        $filename = 'custom_report_'.now()->format('Ymd_His').'.csv';

        return $this->streamCsv($filename, function ($handle) use ($query, $selected): void {
            fputcsv($handle, array_map(fn (string $key): string => self::CUSTOM_COLUMNS[$key], $selected));

            $query->chunk(500, function (Collection $chunk) use ($handle, $selected): void {
                foreach ($chunk as $purchaseRequest) {
                    $row = [];

                    foreach ($selected as $column) {
                        $row[] = match ($column) {
                            'pr_number' => $purchaseRequest->pr_number,
                            'created_at' => optional($purchaseRequest->created_at)->format('Y-m-d H:i'),
                            'requester' => $purchaseRequest->requester?->name,
                            'department' => $purchaseRequest->department?->name,
                            'purpose' => $purchaseRequest->purpose,
                            'date_needed' => optional($purchaseRequest->date_needed)->format('Y-m-d'),
                            'priority' => '',
                            'estimated_total' => number_format((float) $purchaseRequest->estimated_total, 2, '.', ''),
                            'status' => $purchaseRequest->status,
                            default => '',
                        };
                    }

                    fputcsv($handle, $row);
                }
            });
        });
    }

    /**
     * @return Builder<PurchaseRequest>
     */
    private function purchaseRequestQuery(Request $request): Builder
    {
        $query = PurchaseRequest::query()->with(['requester:id,name', 'department:id,name']);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->value());
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->integer('department_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date('date_to'));
        }

        return $query;
    }

    /**
     * @return array{status: string|null, department_id: int|null, date_from: string|null, date_to: string|null}
     */
    private function filtersFrom(Request $request): array
    {
        return [
            'status' => $request->filled('status') ? $request->string('status')->value() : null,
            'department_id' => $request->filled('department_id') ? $request->integer('department_id') : null,
            'date_from' => $request->filled('date_from') ? $request->string('date_from')->value() : null,
            'date_to' => $request->filled('date_to') ? $request->string('date_to')->value() : null,
        ];
    }

    /**
     * @return list<string>
     */
    private function selectedColumns(Request $request): array
    {
        $selected = array_values(array_intersect(
            array_keys(self::CUSTOM_COLUMNS),
            (array) $request->input('columns', ['pr_number', 'created_at', 'requester', 'department', 'estimated_total', 'status']),
        ));

        if ($selected === []) {
            return ['pr_number', 'created_at', 'requester', 'department', 'estimated_total', 'status'];
        }

        return $selected;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function supplierPerformanceRows(): array
    {
        return Supplier::query()
            ->orderBy('business_name')
            ->get()
            ->map(function (Supplier $supplier): array {
                $quotes = Quotation::query()->where('supplier_id', $supplier->id);
                $totalQuotes = (clone $quotes)->count();
                $awards = (clone $quotes)->where('is_winning_bid', true);
                $awardsCount = (clone $awards)->count();
                $awardedTotal = (clone $awards)->sum('total_amount');

                $purchaseOrders = PurchaseOrder::query()->where('supplier_id', $supplier->id);
                $poCount = (clone $purchaseOrders)->count();
                $poDelivered = (clone $purchaseOrders)->whereIn('status', ['delivered', 'completed'])->count();
                $poCompleted = (clone $purchaseOrders)->where('status', 'completed')->count();
                $poTotal = (clone $purchaseOrders)->sum('total_amount');
                $winRate = $totalQuotes > 0 ? round(($awardsCount / $totalQuotes) * 100, 1) : null;

                return [
                    'supplier' => [
                        'id' => $supplier->id,
                        'business_name' => $supplier->business_name,
                        'supplier_code' => $supplier->supplier_code,
                    ],
                    'total_quotes' => $totalQuotes,
                    'awards' => $awardsCount,
                    'win_rate' => $winRate,
                    'awarded_total' => (float) $awardedTotal,
                    'po_count' => $poCount,
                    'po_delivered' => $poDelivered,
                    'po_completed' => $poCompleted,
                    'po_total' => (float) $poTotal,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: array{pr_total: float, po_total: float, pr_count: int, po_count: int}}
     */
    private function budgetUtilization(): array
    {
        $rows = Department::query()
            ->orderBy('name')
            ->get()
            ->map(function (Department $department): array {
                $prTotal = (float) PurchaseRequest::query()->where('department_id', $department->id)->sum('estimated_total');
                $prCount = PurchaseRequest::query()->where('department_id', $department->id)->count();
                $poQuery = PurchaseOrder::query()->whereIn('purchase_request_id', function ($query) use ($department): void {
                    $query->select('id')->from('purchase_requests')->where('department_id', $department->id);
                });
                $poTotal = (float) (clone $poQuery)->sum('total_amount');
                $poCount = (clone $poQuery)->count();
                $poCompleted = (clone $poQuery)->where('status', 'completed')->count();

                return [
                    'department' => [
                        'id' => $department->id,
                        'name' => $department->name,
                        'code' => $department->code,
                    ],
                    'pr_total' => $prTotal,
                    'pr_count' => $prCount,
                    'po_total' => $poTotal,
                    'po_count' => $poCount,
                    'po_completed' => $poCompleted,
                    'utilization_rate' => $prTotal > 0 ? round(($poTotal / $prTotal) * 100, 1) : null,
                ];
            })
            ->values();

        $totals = [
            'pr_total' => (float) $rows->sum('pr_total'),
            'po_total' => (float) $rows->sum('po_total'),
            'pr_count' => (int) $rows->sum('pr_count'),
            'po_count' => (int) $rows->sum('po_count'),
        ];

        return [$rows->all(), $totals];
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function departments(): array
    {
        return Department::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return collect(self::PR_STATUSES)
            ->map(fn (string $status): array => [
                'value' => $status,
                'label' => $status === 'rejected' ? 'Deferred' : str_replace('_', ' ', $status),
            ])
            ->all();
    }

    /**
     * @param  callable(resource): void  $callback
     */
    private function streamCsv(string $filename, callable $callback): StreamedResponse
    {
        return Response::stream(function () use ($callback): void {
            $handle = fopen('php://output', 'w');
            $callback($handle);
            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }
}
