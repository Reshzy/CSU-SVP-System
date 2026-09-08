<?php

namespace App\Http\Requests\Budget;

use App\Models\PurchaseRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait EarmarkValidationRules
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function earmarkRules(): array
    {
        return [
            'legal_basis' => ['required', 'string'],
            'earmark_programs_activities' => ['required', 'string'],
            'earmark_responsibility_center' => ['required', 'string'],
            'earmark_date_to' => ['required', 'date'],
            'earmark_object_expenditures' => ['required', 'array', 'min:1'],
            'earmark_object_expenditures.*.code' => ['nullable', 'string', 'max:50'],
            'earmark_object_expenditures.*.description' => ['required', 'string', 'max:255'],
            'earmark_object_expenditures.*.amount' => ['required', 'numeric', 'min:0'],
            'fund_cluster_code' => ['required', 'string', Rule::in(array_keys(PurchaseRequest::FUND_CLUSTERS))],
            'fund_details' => ['nullable', 'string', 'max:255'],
            'budget_code' => ['nullable', 'string', 'max:50'],
            'current_step_notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function earmarkMessages(): array
    {
        return [
            'legal_basis.required' => 'The legal basis is required.',
            'earmark_programs_activities.required' => 'Programs and activities are required.',
            'earmark_responsibility_center.required' => 'The responsibility center is required.',
            'earmark_date_to.required' => 'The earmark end date is required.',
            'earmark_object_expenditures.required' => 'Add at least one object of expenditure.',
            'earmark_object_expenditures.min' => 'Add at least one object of expenditure.',
            'fund_cluster_code.required' => 'Select a fund cluster.',
        ];
    }
}
