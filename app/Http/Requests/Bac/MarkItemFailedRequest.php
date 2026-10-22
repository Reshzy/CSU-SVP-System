<?php

namespace App\Http\Requests\Bac;

use App\Models\PurchaseRequestItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class MarkItemFailedRequest extends FormRequest
{
    public function authorize(): bool
    {
        $prItem = $this->route('prItem');

        if (! $prItem instanceof PurchaseRequestItem) {
            return false;
        }

        return in_array($prItem->purchaseRequest?->status, ['bac_evaluation', 'bac_approved'], true);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'failure_reason' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }
}
