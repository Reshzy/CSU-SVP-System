<?php

namespace Database\Factories;

use App\Models\PrItemGroup;
use App\Models\PurchaseRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PrItemGroup>
 */
class PrItemGroupFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_request_id' => PurchaseRequest::factory(),
            'group_name' => fake()->words(2, true),
            'group_code' => 'G'.fake()->unique()->numberBetween(1, 99),
            'display_order' => 1,
            'status' => 'pending',
        ];
    }
}
