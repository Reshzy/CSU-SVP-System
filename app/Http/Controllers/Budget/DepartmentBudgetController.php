<?php

namespace App\Http\Controllers\Budget;

use App\Concerns\FlashesToasts;
use App\Http\Controllers\Controller;
use App\Http\Requests\Budget\UpdateDepartmentBudgetRequest;
use App\Models\Department;
use App\Models\DepartmentBudget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentBudgetController extends Controller
{
    use FlashesToasts;

    public function index(Request $request): Response
    {
        $fiscalYear = (int) ($request->query('fiscal_year') ?: now()->year);

        $departments = Department::query()
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $budgets = $departments->map(function (Department $department) use ($fiscalYear): array {
            return $this->present(DepartmentBudget::getOrCreateForDepartment($department->id, $fiscalYear));
        });

        return Inertia::render('budget/departments/index', [
            'budgets' => $budgets,
            'filters' => ['fiscal_year' => $fiscalYear],
        ]);
    }

    public function show(Request $request, Department $department): Response
    {
        $fiscalYear = (int) ($request->query('fiscal_year') ?: now()->year);
        $budget = DepartmentBudget::getOrCreateForDepartment($department->id, $fiscalYear);

        return Inertia::render('budget/departments/show', [
            'budget' => $this->present($budget),
        ]);
    }

    public function edit(Request $request, Department $department): Response
    {
        $fiscalYear = (int) ($request->query('fiscal_year') ?: now()->year);
        $budget = DepartmentBudget::getOrCreateForDepartment($department->id, $fiscalYear);

        return Inertia::render('budget/departments/edit', [
            'budget' => $this->present($budget),
        ]);
    }

    public function update(UpdateDepartmentBudgetRequest $request, Department $department): RedirectResponse
    {
        $fiscalYear = (int) $request->validated('fiscal_year');
        $budget = DepartmentBudget::getOrCreateForDepartment($department->id, $fiscalYear);

        $budget->forceFill([
            'allocated_budget' => $request->validated('allocated_budget'),
            'notes' => $request->validated('notes'),
            'set_by' => $request->user()->id,
        ])->save();

        $this->toast("Updated {$department->name} budget for {$fiscalYear}.");

        return redirect()->route('budget.show', [
            'department' => $department,
            'fiscal_year' => $fiscalYear,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(DepartmentBudget $budget): array
    {
        $budget->loadMissing('department:id,name,code');

        return [
            'id' => $budget->id,
            'department_id' => $budget->department_id,
            'fiscal_year' => $budget->fiscal_year,
            'allocated_budget' => (string) $budget->allocated_budget,
            'utilized_budget' => (string) $budget->utilized_budget,
            'reserved_budget' => (string) $budget->reserved_budget,
            'available_budget' => $budget->getAvailableBudget(),
            'committed_budget' => $budget->getCommittedBudget(),
            'notes' => $budget->notes,
            'department' => $budget->department,
        ];
    }
}
