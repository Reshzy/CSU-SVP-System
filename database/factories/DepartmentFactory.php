<?php

namespace Database\Factories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'College of '.fake()->unique()->word(),
            'code' => Str::upper(fake()->unique()->bothify('???##')),
            'description' => fake()->sentence(),
            'head_name' => fake()->name(),
            'contact_person' => fake()->name(),
            'contact_email' => fake()->unique()->safeEmail(),
            'contact_number' => fake()->numerify('09#########'),
            'is_active' => true,
            'is_archived' => false,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
            'is_archived' => true,
        ]);
    }
}
