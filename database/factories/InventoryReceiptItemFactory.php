<?php

namespace Database\Factories;

use App\Models\InventoryReceipt;
use App\Models\InventoryReceiptItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryReceiptItem>
 */
class InventoryReceiptItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inventory_receipt_id' => InventoryReceipt::factory(),
            'description' => fake()->words(3, true),
            'unit_of_measure' => 'pc',
            'quantity' => 1,
            'unit_price' => 100,
            'total_price' => 100,
        ];
    }
}
