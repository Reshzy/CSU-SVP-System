<?php

namespace Database\Factories;

use App\Models\AppItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AppItem>
 */
class AppItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fiscal_year' => (int) date('Y'),
            'category' => 'OFFICE SUPPLIES',
            'item_code' => Str::upper(fake()->unique()->bothify('####-??-####')),
            'item_name' => Str::title(fake()->unique()->words(3, true)),
            'unit_of_measure' => fake()->randomElement(['piece', 'box', 'ream', 'unit']),
            'unit_price' => fake()->randomFloat(2, 10, 5000),
            'specifications' => fake()->sentence(),
            'is_active' => true,
        ];
    }

    public function forFiscalYear(int $fiscalYear): static
    {
        return $this->state(fn (array $attributes) => [
            'fiscal_year' => $fiscalYear,
        ]);
    }

    /**
     * A SOFTWARE or PART II item, which PS-DBM does not price.
     */
    public function unpriced(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'SOFTWARE',
            'unit_price' => null,
        ]);
    }
}
