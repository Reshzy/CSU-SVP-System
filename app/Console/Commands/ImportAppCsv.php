<?php

namespace App\Console\Commands;

use App\Services\AppItemImporter;
use Illuminate\Console\Command;

/**
 * Loads an APP-CSE worksheet into the PS-DBMS catalog. Run this before
 * importing any department PPMP for the same fiscal year.
 */
class ImportAppCsv extends Command
{
    /**
     * Looked for in the project root when no file argument is given. No CSV is
     * committed, so this is a convenience for local imports only.
     */
    private const DEFAULT_FILE = 'APP-CSE 2025 Form CICS.csv';

    protected $signature = 'app:import
                            {file? : Path to the APP-CSE CSV export}
                            {--year= : Fiscal year to import into, defaults to the current year}';

    protected $description = 'Import an APP-CSE CSV into the PS-DBMS catalog for a fiscal year';

    public function handle(AppItemImporter $importer): int
    {
        $file = (string) ($this->argument('file') ?? base_path(self::DEFAULT_FILE));

        if (! is_readable($file)) {
            $this->error("Cannot read [{$file}]. Pass the path to an APP-CSE CSV export.");

            return self::FAILURE;
        }

        $fiscalYear = (int) ($this->option('year') ?: date('Y'));

        $summary = $importer->import($file, $fiscalYear);

        $this->line("  created: {$summary['created']}");
        $this->line("  updated: {$summary['updated']}");
        $this->line("  skipped (no price): {$summary['skipped']}");

        $this->info("Imported catalog items for fiscal year {$fiscalYear}.");

        return self::SUCCESS;
    }
}
