<?php

namespace App\Http\Requests\Auth;

use App\Concerns\DepartmentValidationRules;
use App\Enums\ApprovalStatus;
use App\Models\Department;
use App\Models\DepartmentRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreDepartmentRequestRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique(Department::class, 'name'),
                Rule::unique(DepartmentRequest::class, 'name')->where('status', ApprovalStatus::Pending->value),
            ],
            'code' => [
                'required',
                'string',
                'max:20',
                'alpha_num',
                Rule::unique(Department::class, 'code'),
                Rule::unique(DepartmentRequest::class, 'code')->where('status', ApprovalStatus::Pending->value),
            ],
            'requester_email' => ['required', 'email', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('code')) {
            $this->merge(['code' => Str::upper($this->string('code')->trim()->value())]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'That department already exists or is already awaiting review.',
            'code.unique' => 'That code already exists or is already awaiting review.',
        ];
    }
}
