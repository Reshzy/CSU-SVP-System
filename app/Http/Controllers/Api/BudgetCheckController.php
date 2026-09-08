<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DepartmentBudget;
use App\Services\PpmpQuarterlyTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BudgetCheckController extends Controller
{
    public function check(Request $request, PpmpQuarterlyTracker $tracker): JsonResponse
    {
        $user = $request->user();

        if ($user->department_id === null) {
            return response()->json([
                'success' => false,
                'message' => 'User is not assigned to a department',
            ], 400);
        }

        $fiscalYear = (int) $request->input('fiscal_year', $tracker->currentFiscalYear());
        $budget = DepartmentBudget::getOrCreateForDepartment($user->department_id, $fiscalYear);

        return response()->json([
            'success' => true,
            'data' => [
                'department_id' => $user->department_id,
                'fiscal_year' => $fiscalYear,
                'allocated_budget' => (float) $budget->allocated_budget,
                'utilized_budget' => (float) $budget->utilized_budget,
                'reserved_budget' => (float) $budget->reserved_budget,
                'available_budget' => $budget->getAvailableBudget(),
                'utilization_percentage' => $budget->getUtilizationPercentage(),
            ],
        ]);
    }

    public function validateAmount(Request $request, PpmpQuarterlyTracker $tracker): JsonResponse
    {
        $user = $request->user();

        if ($user->department_id === null) {
            return response()->json([
                'success' => false,
                'message' => 'User is not assigned to a department',
            ], 400);
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'fiscal_year' => ['nullable', 'integer', 'min:2020', 'max:2100'],
        ]);

        $fiscalYear = (int) ($validated['fiscal_year'] ?? $tracker->currentFiscalYear());
        $amount = (float) $validated['amount'];
        $budget = DepartmentBudget::getOrCreateForDepartment($user->department_id, $fiscalYear);
        $availableBudget = $budget->getAvailableBudget();
        $canReserve = $budget->canReserve($amount);

        return response()->json([
            'success' => true,
            'data' => [
                'can_reserve' => $canReserve,
                'requested_amount' => $amount,
                'available_budget' => $availableBudget,
                'shortage' => $canReserve ? 0 : ($amount - $availableBudget),
            ],
        ]);
    }
}
