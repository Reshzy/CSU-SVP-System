<?php

namespace Database\Factories;

use App\Models\PoSignatory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PoSignatory>
 */
class PoSignatoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'position' => 'ceo',
            'is_active' => true,
        ];
    }
}
