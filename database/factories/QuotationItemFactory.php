<?php

namespace Database\Factories;

use App\Models\PurchaseRequestItem;
use App\Models\Quotation;
use App\Models\QuotationItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuotationItem>
 */
class QuotationItemFactory extends Factory
{
    public function definition(): array
    {
        $unitPrice = fake()->randomFloat(2, 10, 100);

        return [
            'quotation_id' => Quotation::factory(),
            'purchase_request_item_id' => PurchaseRequestItem::factory(),
            'unit_price' => $unitPrice,
            'total_price' => $unitPrice,
            'is_within_abc' => true,
            'is_lowest' => false,
            'is_tied' => false,
            'is_winner' => false,
            'is_withdrawn' => false,
        ];
    }
}
