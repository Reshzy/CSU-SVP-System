<?php

namespace App\Http\Requests\Ppmp;

use App\Concerns\CsvImportValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ImportPpmpRequest extends FormRequest
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
