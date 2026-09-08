<?php

namespace App\Http\Requests\PurchaseRequest;

use App\Enums\PpmpStatus;
use App\Models\DepartmentBudget;
use App\Models\Ppmp;
use App\Models\PpmpItem;
use App\Models\User;
use App\Services\PpmpQuarterlyTracker;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePurchaseRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof User) {
            return false;
        }

        return $user->canCreatePurchaseRequests() && $user->department_id !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'purpose' => ['required', 'string', 'max:255'],
            'justification' => ['required', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.ppmp_item_id' => ['nullable', 'integer', 'exists:ppmp_items,id'],
            'items.*.item_code' => ['nullable', 'string', 'max:100'],
            'items.*.item_name' => ['required', 'string', 'max:255'],
            'items.*.detailed_specifications' => ['nullable', 'string'],
            'items.*.unit_of_measure' => ['required', 'string', 'max:50'],
            'items.*.quantity_requested' => ['required', 'integer', 'min:1'],
            'items.*.estimated_unit_cost' => ['required', 'numeric', 'min:0'],
            'items.*.is_lot' => ['nullable', 'boolean'],
            'items.*.lot_name' => ['nullable', 'string', 'max:255'],
            'items.*.parent_lot_index' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'purpose.required' => 'The purpose of the purchase request is required.',
            'justification.required' => 'The justification for the purchase request is required.',
            'items.required' => 'At least one item must be selected for the purchase request.',
            'items.min' => 'At least one item must be selected for the purchase request.',
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $user = $this->user();

                if (! $user instanceof User || $user->department_id === null) {
                    return;
                }

                $tracker = app(PpmpQuarterlyTracker::class);
                $fiscalYear = $tracker->currentFiscalYear();
                $currentQuarter = $tracker->currentQuarter();
                $quarterLabel = $tracker->quarterLabel($currentQuarter);

                $ppmp = Ppmp::query()
                    ->where('department_id', $user->department_id)
                    ->where('fiscal_year', $fiscalYear)
                    ->first();

                if ($ppmp === null || $ppmp->status !== PpmpStatus::Validated) {
                    $validator->errors()->add(
                        'items',
                        'Your department must have a validated PPMP before creating a purchase request.',
                    );

                    return;
                }

                foreach ((array) $this->input('items', []) as $index => $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $this->validatePpmpLine($validator, (int) $index, $item, $currentQuarter, $quarterLabel);
                }
            },
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function validatePpmpLine(
        Validator $validator,
        int $index,
        array $item,
        int $currentQuarter,
        string $quarterLabel,
    ): void {
        if (isset($item['parent_lot_index']) && $item['parent_lot_index'] !== '') {
            return;
        }

        $ppmpItemId = $item['ppmp_item_id'] ?? null;

        if ($ppmpItemId === null || $ppmpItemId === '') {
            return;
        }

        $ppmpItem = PpmpItem::query()->with(['ppmp', 'appItem'])->find($ppmpItemId);

        if ($ppmpItem === null) {
            $validator->errors()->add("items.{$index}.ppmp_item_id", 'The selected PPMP item does not exist.');

            return;
        }

        $itemName = $ppmpItem->appItem?->item_name ?? $ppmpItem->appItem?->item_code ?? 'this item';

        if ($ppmpItem->ppmp->status !== PpmpStatus::Validated) {
            $validator->errors()->add(
                "items.{$index}.ppmp_item_id",
                "The PPMP for item '{$itemName}' must be validated before creating a PR.",
            );

            return;
        }

        if (! $ppmpItem->hasQuantityForQuarter($currentQuarter)) {
            $validator->errors()->add(
                "items.{$index}.ppmp_item_id",
                "Item '{$itemName}' is not allocated for the current quarter (Q{$currentQuarter} - {$quarterLabel}).",
            );

            return;
        }

        $remainingQty = $ppmpItem->getRemainingQuantity($currentQuarter, $this->excludedPurchaseRequestId());

        if ($remainingQty <= 0) {
            $validator->errors()->add(
                "items.{$index}.quantity_requested",
                "Item '{$itemName}' has no remaining quantity for Q{$currentQuarter} ({$quarterLabel}).",
            );

            return;
        }

        $quantity = (int) ($item['quantity_requested'] ?? 0);

        if ($quantity > $remainingQty) {
            $validator->errors()->add(
                "items.{$index}.quantity_requested",
                "Requested quantity ({$quantity}) for '{$itemName}' exceeds remaining quantity ({$remainingQty}) for Q{$currentQuarter} ({$quarterLabel}).",
            );
        }
    }

    /**
     * Lot children are display-only; the header (or standalone) carries the cost.
     */
    public function calculateTotalCost(): float
    {
        $total = 0.0;

        foreach ((array) $this->validated('items') as $item) {
            if (isset($item['parent_lot_index']) && $item['parent_lot_index'] !== '') {
                continue;
            }

            $quantity = ! empty($item['is_lot']) ? 1 : (int) ($item['quantity_requested'] ?? 0);
            $total += (float) ($item['estimated_unit_cost'] ?? 0) * $quantity;
        }

        return $total;
    }

    /**
     * @return array{can_reserve: bool, error?: string, available?: float, required?: float}
     */
    public function checkBudgetAvailability(): array
    {
        $user = $this->user();

        if (! $user instanceof User || $user->department_id === null) {
            return [
                'can_reserve' => false,
                'error' => 'User must be assigned to a department.',
            ];
        }

        $fiscalYear = app(PpmpQuarterlyTracker::class)->currentFiscalYear();
        $budget = DepartmentBudget::getOrCreateForDepartment($user->department_id, $fiscalYear);
        $totalCost = $this->calculateTotalCost();

        if (! $budget->canReserve($totalCost)) {
            return [
                'can_reserve' => false,
                'error' => 'Insufficient budget. Available: ₱'.number_format($budget->getAvailableBudget(), 2).', Required: ₱'.number_format($totalCost, 2),
                'available' => $budget->getAvailableBudget(),
                'required' => $totalCost,
            ];
        }

        return [
            'can_reserve' => true,
            'available' => $budget->getAvailableBudget(),
            'required' => $totalCost,
        ];
    }

    /**
     * Replacement forms exclude the original request so its qty is available.
     */
    protected function excludedPurchaseRequestId(): ?int
    {
        return null;
    }
}
