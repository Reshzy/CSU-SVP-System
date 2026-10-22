<?php

namespace App\Http\Requests\Bac;

use App\Models\QuotationItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class WithdrawQuotationItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $quotationItem = $this->route('quotationItem');

        if (! $quotationItem instanceof QuotationItem) {
            return false;
        }

        $status = $quotationItem->quotation?->purchaseRequest?->status;

        return in_array($status, ['bac_evaluation', 'bac_approved'], true);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'withdrawal_reason' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }
}
