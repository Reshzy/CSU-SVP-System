<?php

namespace App\Http\Controllers\Budget;

use App\Concerns\FlashesToasts;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\DepartmentBudget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentBudgetController extends Controller
{
    use FlashesToasts;

    public function index(): Response
    {
        $budgets = DepartmentBudget::query()
            ->with(['department:id,name,code', 'setBy:id,name'])
            ->orderByDesc('fiscal_year')
            ->paginate(20);

        return Inertia::render('budget/departments/index', [
            'budgets' => $budgets,
            'departments' => Department::query()->orderBy('name')->get(['id', 'name', 'code']),
            'fiscalYear' => (int) now()->format('Y'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'fiscal_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'allocated_budget' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $budget = DepartmentBudget::getOrCreateForDepartment(
            (int) $validated['department_id'],
            (int) $validated['fiscal_year'],
        );

        $budget->fill([
            'allocated_budget' => $validated['allocated_budget'],
            'notes' => $validated['notes'] ?? null,
            'set_by' => $request->user()->id,
        ])->save();

        $this->toast('Department budget saved.');

        return back();
    }

    public function update(Request $request, DepartmentBudget $departmentBudget): RedirectResponse
    {
        $validated = $request->validate([
            'allocated_budget' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $departmentBudget->fill([
            'allocated_budget' => $validated['allocated_budget'],
            'notes' => $validated['notes'] ?? null,
            'set_by' => $request->user()->id,
        ])->save();

        $this->toast('Department budget updated.');

        return back();
    }
}
