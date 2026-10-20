<?php

namespace Database\Factories;

use App\Models\PrItemGroup;
use App\Models\RfqGeneration;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RfqGeneration>
 */
class RfqGenerationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pr_item_group_id' => PrItemGroup::factory(),
            'rfq_number' => 'RFQ-'.now()->format('my').'-'.fake()->unique()->numerify('####'),
            'generated_by' => User::factory(),
            'generated_at' => now(),
            'file_path' => 'rfq/test.docx',
        ];
    }
}
