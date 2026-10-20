<?php

namespace Database\Factories;

use App\Models\DisbursementVoucher;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DisbursementVoucher>
 */
class DisbursementVoucherFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'voucher_number' => DisbursementVoucher::generateNextVoucherNumber(),
            'purchase_order_id' => PurchaseOrder::factory(),
            'supplier_id' => Supplier::factory(),
            'amount' => 100,
            'voucher_date' => now()->toDateString(),
            'status' => 'submitted',
            'prepared_by' => User::factory(),
        ];
    }
}
