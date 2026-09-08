<?php

namespace App\Http\Requests\Supply;

use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePurchaseRequestStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $purchaseRequest = $this->route('purchase_request');

        if (! $user instanceof User || ! $purchaseRequest instanceof PurchaseRequest) {
            return false;
        }

        return $user->can('update', $purchaseRequest);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(['start_review', 'activate', 'return', 'reject', 'cancel'])],
            'remarks' => ['required_if:action,return', 'nullable', 'string', 'max:2000'],
            'rejection_reason' => ['required_if:action,reject', 'nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'remarks.required_if' => 'Remarks are required when returning a request to the department.',
            'rejection_reason.required_if' => 'Give a reason for deferring this purchase request.',
        ];
    }
}
