<?php

namespace App\Http\Requests\Bac;

use App\Models\PurchaseRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePrItemGroupsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $purchaseRequest = $this->route('purchaseRequest');

        return $purchaseRequest instanceof PurchaseRequest && $purchaseRequest->canManageGroups();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $purchaseRequest = $this->route('purchaseRequest');
        $purchaseRequestId = $purchaseRequest instanceof PurchaseRequest ? $purchaseRequest->id : 0;

        return [
            'groups' => ['required', 'array', 'min:1'],
            'groups.*.name' => ['required', 'string', 'max:255'],
            'groups.*.items' => ['required', 'array', 'min:1'],
            'groups.*.items.*' => ['required', 'integer', Rule::exists('purchase_request_items', 'id')->where('purchase_request_id', $purchaseRequestId)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $itemIds = collect($this->input('groups', []))
                ->pluck('items')
                ->flatten()
                ->filter()
                ->map(fn (mixed $id): int => (int) $id);

            if ($itemIds->count() !== $itemIds->unique()->count()) {
                $validator->errors()->add('groups', 'An item cannot be assigned to more than one group.');
            }

            $purchaseRequest = $this->route('purchaseRequest');

            if (! $purchaseRequest instanceof PurchaseRequest) {
                return;
            }

            $quotableIds = $purchaseRequest->quotableItems()
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->sort()
                ->values();
            $assignedIds = $itemIds->unique()->sort()->values();

            if ($quotableIds->count() !== $assignedIds->count() || $quotableIds->diff($assignedIds)->isNotEmpty()) {
                $validator->errors()->add('groups', 'Every item must be assigned to a group.');
            }
        });
    }
}
