<?php

namespace Database\Factories;

use App\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Position>
 */
class PositionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->jobTitle(),
        ];
    }

    /**
     * The default position offered on the registration form.
     */
    public function employee(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Employee',
        ]);
    }
}
