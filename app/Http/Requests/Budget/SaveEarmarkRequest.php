<?php

namespace App\Http\Requests\Budget;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveEarmarkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('Budget Office') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'legal_basis' => ['required', 'string'],
            'earmark_programs_activities' => ['required', 'string'],
            'earmark_responsibility_center' => ['required', 'string'],
            'earmark_date_to' => ['required', 'date'],
            'earmark_object_expenditures' => ['nullable', 'array'],
            'earmark_object_expenditures.*.description' => ['nullable', 'string'],
            'earmark_object_expenditures.*.amount' => ['nullable', 'numeric'],
            'current_step_notes' => ['nullable', 'string'],
        ];
    }
}
