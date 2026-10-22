<?php

namespace Database\Factories;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequestItem;
use App\Models\QuotationItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrderItem>
 */
class PurchaseOrderItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'purchase_request_item_id' => PurchaseRequestItem::factory(),
            'quotation_item_id' => QuotationItem::factory(),
            'quantity' => 1,
            'unit_price' => 100,
            'total_price' => 100,
        ];
    }
}
