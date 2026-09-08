<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fileName = fake()->unique()->slug().'.pdf';

        return [
            'documentable_type' => User::class,
            'documentable_id' => User::factory(),
            'document_type' => 'other',
            'title' => fake()->sentence(3),
            'file_path' => 'user-id-proofs/'.now()->format('Y/m').'/'.$fileName,
            'file_name' => $fileName,
            'mime_type' => 'application/pdf',
            'file_size' => fake()->numberBetween(1000, 500000),
            'status' => 'draft',
        ];
    }

    /**
     * A government ID uploaded during registration.
     */
    public function idProofFor(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'documentable_type' => User::class,
            'documentable_id' => $user->id,
            'document_type' => 'other',
            'uploaded_by' => $user->id,
        ]);
    }
}
