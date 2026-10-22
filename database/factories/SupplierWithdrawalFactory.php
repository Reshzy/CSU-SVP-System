<?php

namespace Database\Factories;

use App\Models\PurchaseRequestItem;
use App\Models\QuotationItem;
use App\Models\Supplier;
use App\Models\SupplierWithdrawal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierWithdrawal>
 */
class SupplierWithdrawalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quotation_item_id' => QuotationItem::factory(),
            'supplier_id' => Supplier::factory(),
            'purchase_request_item_id' => PurchaseRequestItem::factory(),
            'withdrawal_reason' => fake()->sentence(),
            'withdrawn_at' => now(),
            'withdrawn_by' => User::factory(),
            'resulted_in_failure' => false,
        ];
    }
}
