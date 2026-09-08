<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\DepartmentBudget;
use Illuminate\Database\Seeder;

/**
 * Gives every department a current-year envelope so a Dean with a validated
 * PPMP can submit a request after `db:seed`.
 */
class DepartmentBudgetSeeder extends Seeder
{
    public const DEFAULT_ALLOCATION = 5_000_000;

    public function run(): void
    {
        $fiscalYear = (int) date('Y');

        Department::query()->each(function (Department $department) use ($fiscalYear): void {
            DepartmentBudget::query()->firstOrCreate(
                [
                    'department_id' => $department->id,
                    'fiscal_year' => $fiscalYear,
                ],
                [
                    'allocated_budget' => self::DEFAULT_ALLOCATION,
                    'utilized_budget' => 0,
                    'reserved_budget' => 0,
                ],
            );
        });
    }
}
