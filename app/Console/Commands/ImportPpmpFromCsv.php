<?php

namespace App\Console\Commands;

use App\Models\Department;
use App\Models\Ppmp;
use App\Services\PpmpImporter;
use Illuminate\Console\Command;

/**
 * Loads quarterly quantities from an APP-CSE worksheet into one department's
 * PPMP. The catalog for that fiscal year must already be imported.
 */
class ImportPpmpFromCsv extends Command
{
    protected $signature = 'ppmp:import-csv
                            {file : Path to the APP-CSE CSV export}
                            {--year= : Fiscal year of the plan, defaults to the current year}
                            {--department= : Department id or code the plan belongs to}';

    protected $description = 'Import an APP-CSE CSV into a department PPMP';

    public function handle(PpmpImporter $importer): int
    {
        $file = (string) $this->argument('file');

        if (! is_readable($file)) {
            $this->error("Cannot read [{$file}]. Pass the path to an APP-CSE CSV export.");

            return self::FAILURE;
        }

        $department = $this->resolveDepartment();

        if ($department === null) {
            $this->error('Pass --department= with the id or code of an existing department.');

            return self::FAILURE;
        }

        $fiscalYear = (int) ($this->option('year') ?: date('Y'));

        $ppmp = Ppmp::getOrCreateForDepartment($department->id, $fiscalYear);

        $summary = $importer->import($file, $ppmp);

        $this->line("  created: {$summary['created']}");
        $this->line("  updated: {$summary['updated']}");
        $this->line("  skipped (not in catalog): {$summary['skipped_missing_catalog']}");
        $this->line("  skipped (no quantity): {$summary['skipped_zero_quantity']}");
        $this->line("  skipped (no price): {$summary['skipped_unpriced']}");

        $this->info("Imported {$department->code} PPMP for fiscal year {$fiscalYear}.");

        return self::SUCCESS;
    }

    private function resolveDepartment(): ?Department
    {
        $department = (string) $this->option('department');

        if ($department === '') {
            return null;
        }

        return Department::query()
            ->when(
                is_numeric($department),
                fn ($query) => $query->where('id', (int) $department),
                fn ($query) => $query->where('code', $department),
            )
            ->first();
    }
}
