<?php

namespace Database\Factories;

use App\Models\PurchaseRequest;
use App\Models\Quotation;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quotation>
 */
class QuotationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'quotation_number' => 'Q-'.now()->format('my').'-'.fake()->unique()->numerify('####'),
            'purchase_request_id' => PurchaseRequest::factory(),
            'supplier_id' => Supplier::factory(),
            'quotation_date' => now()->toDateString(),
            'total_amount' => 0,
            'exceeds_abc' => false,
            'bac_status' => 'pending_evaluation',
            'is_winning_bid' => false,
        ];
    }
}
