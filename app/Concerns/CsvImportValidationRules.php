<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Shared rules for the two APP-CSE uploads. The catalog import and the PPMP
 * import take the same worksheet, so they take the same file constraints.
 */
trait CsvImportValidationRules
{
    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function csvImportRules(): array
    {
        return [
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
            'fiscal_year' => ['required', 'integer', 'min:2020', 'max:2100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function csvImportMessages(): array
    {
        return [
            'csv_file.mimes' => 'Upload the APP-CSE worksheet as a CSV file.',
            'csv_file.max' => 'The CSV may not be larger than 10 MB.',
        ];
    }
}
