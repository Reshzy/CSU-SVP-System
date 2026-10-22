<?php

namespace Database\Factories;

use App\Models\InventoryReceipt;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryReceipt>
 */
class InventoryReceiptFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'received_date' => now()->toDateString(),
            'status' => 'posted',
            'received_by' => User::factory(),
        ];
    }
}
