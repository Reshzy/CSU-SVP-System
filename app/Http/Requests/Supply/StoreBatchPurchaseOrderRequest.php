<?php

namespace App\Http\Requests\Supply;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreBatchPurchaseOrderRequest extends FormRequest
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
            'purchase_orders' => ['required', 'array', 'min:1'],
            'purchase_orders.*.supplier_id' => ['required', 'exists:suppliers,id'],
            'purchase_orders.*.quotation_id' => ['required', 'exists:quotations,id'],
            'purchase_orders.*.tin' => ['nullable', 'string', 'max:255'],
            'purchase_orders.*.supplier_name_override' => ['nullable', 'string', 'max:255'],
            'purchase_orders.*.funds_cluster' => ['nullable', 'string', 'max:255'],
            'purchase_orders.*.funds_available' => ['nullable', 'numeric', 'min:0'],
            'purchase_orders.*.ors_burs_no' => ['nullable', 'string', 'max:255'],
            'purchase_orders.*.ors_burs_date' => ['nullable', 'date'],
            'purchase_orders.*.total_amount' => ['nullable', 'numeric', 'min:0'],
            'purchase_orders.*.delivery_address' => ['required', 'string'],
            'purchase_orders.*.delivery_date_required' => ['required', 'date', 'after_or_equal:today'],
            'purchase_orders.*.terms_and_conditions' => ['required', 'string'],
            'purchase_orders.*.special_instructions' => ['nullable', 'string'],
            'purchase_orders.*.items' => ['required', 'array', 'min:1'],
            'purchase_orders.*.items.*.purchase_request_item_id' => ['required', 'exists:purchase_request_items,id'],
            'purchase_orders.*.items.*.quotation_item_id' => ['required', 'exists:quotation_items,id'],
            'purchase_orders.*.items.*.quantity' => ['required', 'integer', 'min:1'],
            'purchase_orders.*.items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'purchase_orders.*.items.*.total_price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
