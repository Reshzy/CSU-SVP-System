<?php

namespace App\Http\Requests\Budget;

use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ApproveEarmarkRequest extends FormRequest
{
    use EarmarkValidationRules;

    public function authorize(): bool
    {
        $user = $this->user();
        $purchaseRequest = $this->route('purchase_request');

        if (! $user instanceof User || ! $purchaseRequest instanceof PurchaseRequest) {
            return false;
        }

        return $user->can('earmark', $purchaseRequest);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->earmarkRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->earmarkMessages();
    }
}
