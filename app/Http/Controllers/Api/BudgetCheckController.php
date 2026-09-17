<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DepartmentBudget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BudgetCheckController extends Controller
{
    public function check(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'fiscal_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        $departmentId = (int) ($validated['department_id'] ?? $request->user()->department_id);
        $fiscalYear = (int) ($validated['fiscal_year'] ?? now()->year);

        $budget = DepartmentBudget::getOrCreateForDepartment($departmentId, $fiscalYear);

        return response()->json([
            'department_id' => $departmentId,
            'fiscal_year' => $fiscalYear,
            'allocated_budget' => (float) $budget->allocated_budget,
            'utilized_budget' => (float) $budget->utilized_budget,
            'reserved_budget' => (float) $budget->reserved_budget,
            'available_budget' => $budget->availableBudget(),
            'committed_budget' => $budget->committedBudget(),
        ]);
    }

    public function validateAmount(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'fiscal_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        $departmentId = (int) ($validated['department_id'] ?? $request->user()->department_id);
        $fiscalYear = (int) ($validated['fiscal_year'] ?? now()->year);
        $amount = (float) $validated['amount'];

        $budget = DepartmentBudget::getOrCreateForDepartment($departmentId, $fiscalYear);
        $available = $budget->availableBudget();

        return response()->json([
            'valid' => $available >= $amount,
            'available_budget' => $available,
            'requested_amount' => $amount,
        ]);
    }
}
