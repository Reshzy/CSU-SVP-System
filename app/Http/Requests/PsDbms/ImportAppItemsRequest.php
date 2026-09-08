<?php

namespace App\Http\Requests\PsDbms;

use App\Concerns\CsvImportValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ImportAppItemsRequest extends FormRequest
{
    use CsvImportValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->csvImportRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->csvImportMessages();
    }
}
