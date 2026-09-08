<?php

namespace App\Http\Requests\Ppmp;

use App\Models\AppItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePpmpRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.app_item_id' => ['required', 'distinct', Rule::exists(AppItem::class, 'id')],
            'items.*.q1_quantity' => ['required', 'integer', 'min:0'],
            'items.*.q2_quantity' => ['required', 'integer', 'min:0'],
            'items.*.q3_quantity' => ['required', 'integer', 'min:0'],
            'items.*.q4_quantity' => ['required', 'integer', 'min:0'],
            'items.*.custom_unit_price' => ['nullable', 'numeric', 'min:0.01'],
        ];
    }

    /**
     * Lines with no quantity in any quarter are dropped rather than stored, so
     * the plan must hold at least one that does, and each of those needs a
     * price we can cost it at.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $items = $this->plannedItems();

                if ($items === []) {
                    $validator->errors()->add('items', 'Plan a quantity for at least one item.');

                    return;
                }

                $catalogPrices = AppItem::query()
                    ->whereIn('id', array_column($items, 'app_item_id'))
                    ->pluck('unit_price', 'id');

                foreach ($items as $index => $item) {
                    $unitPrice = $item['custom_unit_price'] ?? $catalogPrices[$item['app_item_id']] ?? null;

                    if ($unitPrice === null || (float) $unitPrice <= 0) {
                        $validator->errors()->add(
                            "items.{$index}.custom_unit_price",
                            'This item has no PS-DBMS price. Enter a unit price for it.',
                        );
                    }
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Add at least one item to the plan.',
            'items.*.app_item_id.distinct' => 'An item may only appear once on the plan.',
        ];
    }

    /**
     * Submitted lines that carry a quantity, keyed by their original index so
     * validation errors land on the right row.
     *
     * @return array<int, array{app_item_id: int, custom_unit_price: string|null, quarters: array<int, int>}>
     */
    public function plannedItems(): array
    {
        $planned = [];

        foreach ((array) $this->input('items', []) as $index => $item) {
            if (! is_array($item) || ! isset($item['app_item_id'])) {
                continue;
            }

            $quarters = [
                1 => (int) ($item['q1_quantity'] ?? 0),
                2 => (int) ($item['q2_quantity'] ?? 0),
                3 => (int) ($item['q3_quantity'] ?? 0),
                4 => (int) ($item['q4_quantity'] ?? 0),
            ];

            if (array_sum($quarters) <= 0) {
                continue;
            }

            $planned[(int) $index] = [
                'app_item_id' => (int) $item['app_item_id'],
                'custom_unit_price' => $item['custom_unit_price'] ?? null,
                'quarters' => $quarters,
            ];
        }

        return $planned;
    }
}
