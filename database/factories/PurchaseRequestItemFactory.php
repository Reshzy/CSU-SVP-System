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
        return [
            'purchase_request_id' => PurchaseRequest::factory(),
            'ppmp_item_id' => PpmpItem::factory(),
            'ppmp_quarter' => 1,
            'quantity_requested' => fake()->numberBetween(1, 10),
        ];
    }
}
