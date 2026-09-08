<?php

namespace Database\Factories;

use App\Models\AppItem;
use App\Models\Ppmp;
use App\Models\PpmpItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PpmpItem>
 */
class PpmpItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quarterly = fake()->numberBetween(1, 25);
        $unitCost = fake()->randomFloat(2, 10, 5000);

        return [
            'ppmp_id' => Ppmp::factory(),
            'app_item_id' => AppItem::factory(),
            'q1_quantity' => $quarterly,
            'q2_quantity' => $quarterly,
            'q3_quantity' => $quarterly,
            'q4_quantity' => $quarterly,
            'total_quantity' => $quarterly * 4,
            'estimated_unit_cost' => $unitCost,
            'estimated_total_cost' => $quarterly * 4 * $unitCost,
        ];
    }

    /**
     * The same quantity planned in every quarter.
     */
    public function plannedEachQuarter(int $quantity): static
    {
        return $this->state(fn (array $attributes) => [
            'q1_quantity' => $quantity,
            'q2_quantity' => $quantity,
            'q3_quantity' => $quantity,
            'q4_quantity' => $quantity,
            'total_quantity' => $quantity * 4,
            'estimated_total_cost' => $quantity * 4 * (float) $attributes['estimated_unit_cost'],
        ]);
    }
}
