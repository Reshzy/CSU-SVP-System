<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

trait DepartmentValidationRules
{
    /**
     * Rules shared by CEO department CRUD and guest department requests.
     * Uniqueness is left to the caller, since a pending request is checked
     * against `department_requests` while CRUD is checked against `departments`.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function departmentDetailRules(): array
    {
        return [
            'description' => ['nullable', 'string', 'max:1000'],
            'head_name' => ['nullable', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:255'],
        ];
    }
}
