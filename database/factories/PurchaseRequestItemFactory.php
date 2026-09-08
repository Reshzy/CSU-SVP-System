<?php

namespace Database\Factories;

use App\Models\PpmpItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseRequestItem>
 */
class PurchaseRequestItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 10);
        $unitCost = fake()->randomFloat(2, 10, 100);

        return [
            'purchase_request_id' => PurchaseRequest::factory(),
            'ppmp_item_id' => PpmpItem::factory(),
            'item_name' => fake()->words(3, true),
            'unit_of_measure' => 'pc',
            'ppmp_quarter' => 1,
            'quantity_requested' => $quantity,
            'estimated_unit_cost' => $unitCost,
            'estimated_total_cost' => $quantity * $unitCost,
            'is_lot' => false,
            'item_status' => 'pending',
            'procurement_status' => 'pending',
        ];
    }
}
