<?php

namespace App\Http\Requests\PurchaseRequest;

use App\Enums\PpmpStatus;
use App\Models\Ppmp;
use App\Models\PpmpItem;
use App\Models\PurchaseRequest;
use App\Services\PpmpQuarterlyTracker;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePurchaseRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->canCreatePurchaseRequests();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'pr_title' => ['required', 'string', 'max:255'],
            'purpose' => ['required', 'string'],
            'justification' => ['nullable', 'string'],
            'date_needed' => ['required', 'date', 'after_or_equal:today'],
            'fund_cluster_code' => ['required', 'string', Rule::in(['01', '05', '06', '07'])],
            'fund_details' => ['nullable', 'string'],
            'budget_code' => ['nullable', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.ppmp_item_id' => ['required', 'integer', 'exists:ppmp_items,id'],
            'items.*.quantity_requested' => ['required', 'integer', 'min:1'],
            'items.*.is_lot' => ['sometimes', 'boolean'],
            'items.*.lot_name' => ['nullable', 'string', 'max:255'],
            'items.*.parent_lot_index' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $user = $this->user();

            if ($user === null || $user->department_id === null) {
                $validator->errors()->add('items', 'You must belong to a department to create a purchase request.');

                return;
            }

            $tracker = app(PpmpQuarterlyTracker::class);
            $fiscalYear = $tracker->currentFiscalYear();
            $quarter = $tracker->currentQuarter();

            $ppmp = Ppmp::query()
                ->where('department_id', $user->department_id)
                ->where('fiscal_year', $fiscalYear)
                ->first();

            if ($ppmp === null || $ppmp->status !== PpmpStatus::Validated) {
                $validator->errors()->add('items', 'Your department must have a validated PPMP for the current fiscal year.');

                return;
            }

            $items = $this->input('items', []);

            foreach ($items as $index => $item) {
                if (! empty($item['is_lot'])) {
                    continue;
                }

                $ppmpItemId = (int) ($item['ppmp_item_id'] ?? 0);
                $quantity = (int) ($item['quantity_requested'] ?? 0);

                $ppmpItem = PpmpItem::query()
                    ->whereKey($ppmpItemId)
                    ->where('ppmp_id', $ppmp->id)
                    ->first();

                if ($ppmpItem === null) {
                    $validator->errors()->add("items.{$index}.ppmp_item_id", 'The selected PPMP item is invalid for your department plan.');

                    continue;
                }

                $planned = $ppmpItem->getQuarterlyQuantity($quarter);

                if ($planned <= 0) {
                    $validator->errors()->add("items.{$index}.quantity_requested", 'No PPMP allocation exists for the current quarter.');

                    continue;
                }

                $remaining = $ppmpItem->getRemainingQuantity($quarter);

                if ($quantity > $remaining) {
                    $validator->errors()->add(
                        "items.{$index}.quantity_requested",
                        "Requested quantity exceeds remaining PPMP quantity ({$remaining}) for the current quarter.",
                    );
                }
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function purchaseRequestAttributes(): array
    {
        $fundCluster = (string) $this->validated('fund_cluster_code');

        return [
            'pr_title' => $this->validated('pr_title'),
            'purpose' => $this->validated('purpose'),
            'justification' => $this->validated('justification'),
            'date_needed' => $this->validated('date_needed'),
            'fund_cluster_code' => $fundCluster,
            'funding_source' => PurchaseRequest::formatFundingSourceFromFundCluster($fundCluster),
            'fund_details' => $this->validated('fund_details'),
            'budget_code' => $this->validated('budget_code'),
            'has_ppmp' => true,
        ];
    }
}
