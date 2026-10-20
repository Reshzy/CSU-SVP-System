<?php

namespace Database\Factories;

use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'po_number' => PurchaseOrder::generateNextPoNumber(),
            'purchase_request_id' => PurchaseRequest::factory(),
            'supplier_id' => Supplier::factory(),
            'po_date' => now()->toDateString(),
            'total_amount' => 0,
            'delivery_address' => fake()->address(),
            'delivery_date_required' => now()->addWeeks(2)->toDateString(),
            'terms_and_conditions' => 'Standard government procurement terms under RA 9184 apply.',
            'status' => 'pending_approval',
        ];
    }
}
