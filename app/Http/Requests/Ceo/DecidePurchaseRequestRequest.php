<?php

namespace App\Http\Requests\Ceo;

use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecidePurchaseRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $purchaseRequest = $this->route('purchase_request');

        if (! $user instanceof User || ! $purchaseRequest instanceof PurchaseRequest) {
            return false;
        }

        return $user->can('ceoDecide', $purchaseRequest);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', Rule::in(['approve', 'reject'])],
            'rejection_reason' => ['required_if:decision,reject', 'nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rejection_reason.required_if' => 'Give a reason for deferring this purchase request.',
        ];
    }
}
