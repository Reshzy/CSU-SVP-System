<?php

namespace App\Http\Requests\Supply;

use App\Models\PurchaseRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Supply officers may only correct the narrative fields of a purchase request:
 * purpose, justification and per-item specifications. Quantities and costs are
 * intentionally absent so they are never part of the validated payload.
 */
class UpdatePurchaseRequestDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var PurchaseRequest $purchaseRequest */
        $purchaseRequest = $this->route('purchaseRequest');

        return $this->user()?->can('view', $purchaseRequest) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var PurchaseRequest $purchaseRequest */
        $purchaseRequest = $this->route('purchaseRequest');

        return [
            'purpose' => ['required', 'string', 'max:5000'],
            'justification' => ['nullable', 'string', 'max:5000'],
            'items' => ['sometimes', 'array'],
            'items.*.id' => [
                'required',
                'integer',
                Rule::exists('purchase_request_items', 'id')
                    ->where('purchase_request_id', $purchaseRequest->id)
                    ->where('is_lot', false),
            ],
            'items.*.detailed_specifications' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
