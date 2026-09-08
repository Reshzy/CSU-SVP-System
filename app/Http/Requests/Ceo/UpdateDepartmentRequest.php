<?php

namespace App\Http\Requests\Ceo;

use App\Concerns\DepartmentValidationRules;
use App\Models\Department;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;

class UpdateDepartmentRequest extends FormRequest
{
    use DepartmentValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $department = $this->department();

        return [
            ...$this->departmentDetailRules(),
            'name' => ['required', 'string', 'max:255', Rule::unique(Department::class, 'name')->ignore($department)],
            'code' => ['required', 'string', 'max:20', 'alpha_num', Rule::unique(Department::class, 'code')->ignore($department)],
            'is_active' => ['boolean'],
            'is_archived' => ['boolean'],
        ];
    }

    /**
     * The department being edited, which route model binding has already
     * resolved for every route using this request.
     */
    private function department(): Department
    {
        $department = $this->route('department');

        if (! $department instanceof Department) {
            throw new RuntimeException('The department route parameter is not bound to a Department.');
        }

        return $department;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('code')) {
            $this->merge(['code' => Str::upper($this->string('code')->trim()->value())]);
        }
    }
}
