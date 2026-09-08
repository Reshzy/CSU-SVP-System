<?php

namespace App\Http\Requests\Ceo;

use App\Concerns\DepartmentValidationRules;
use App\Models\Department;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreDepartmentRequest extends FormRequest
{
    use DepartmentValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->departmentDetailRules(),
            'name' => ['required', 'string', 'max:255', Rule::unique(Department::class, 'name')],
            'code' => ['required', 'string', 'max:20', 'alpha_num', Rule::unique(Department::class, 'code')],
            'is_active' => ['boolean'],
            'is_archived' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('code')) {
            $this->merge(['code' => Str::upper($this->string('code')->trim()->value())]);
        }
    }
}
