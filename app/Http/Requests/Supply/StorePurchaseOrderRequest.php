<?php

namespace App\Http\Requests\Supply;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'pr_item_group_id' => ['nullable', 'exists:pr_item_groups,id'],
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'quotation_id' => ['nullable', 'exists:quotations,id'],
            'tin' => ['nullable', 'string', 'max:255'],
            'supplier_name_override' => ['nullable', 'string', 'max:255'],
            'funds_cluster' => ['nullable', 'string', 'max:255'],
            'funds_available' => ['nullable', 'numeric', 'min:0'],
            'ors_burs_no' => ['nullable', 'string', 'max:255'],
            'ors_burs_date' => ['nullable', 'date'],
            'total_amount' => ['nullable', 'numeric', 'min:0'],
            'delivery_address' => ['required', 'string'],
            'delivery_date_required' => ['required', 'date'],
            'terms_and_conditions' => ['required', 'string'],
            'special_instructions' => ['nullable', 'string'],
        ];
    }
}
