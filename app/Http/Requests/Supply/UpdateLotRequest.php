<?php

namespace App\Http\Requests\Supply;

use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateLotRequest extends FormRequest
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
            'lot_name' => ['sometimes', 'required', 'string', 'max:255'],
            'item_ids' => ['sometimes', 'required', 'array', 'min:2'],
            'item_ids.*' => ['integer', 'distinct', 'exists:purchase_request_items,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'item_ids.min' => 'A lot needs at least two standalone items.',
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->has('item_ids')) {
                    return;
                }

                $purchaseRequest = $this->route('purchase_request');
                $lotId = (int) $this->route('lot');

                if (! $purchaseRequest instanceof PurchaseRequest) {
                    return;
                }

                $itemIds = array_map('intval', (array) $this->input('item_ids', []));

                if (count($itemIds) < 2) {
                    return;
                }

                $eligible = PurchaseRequestItem::query()
                    ->where('purchase_request_id', $purchaseRequest->id)
                    ->whereIn('id', $itemIds)
                    ->where('is_lot', false)
                    ->where(function ($query) use ($lotId): void {
                        $query->whereNull('parent_lot_id')
                            ->orWhere('parent_lot_id', $lotId);
                    })
                    ->count();

                if ($eligible !== count($itemIds)) {
                    $validator->errors()->add(
                        'item_ids',
                        'Lot children must be standalone items on this purchase request.',
                    );
                }
            },
        ];
    }
}
