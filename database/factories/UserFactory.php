<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'tmdb_token' => 'test-tmdb-token',
        ];
    }

    /**
     * Utente senza token TMDB (blocca il gate che ne richiede uno).
     */
    public function withoutTmdbToken(): static
    {
        return $this->state(fn (array $attributes) => [
            'tmdb_token' => null,
        ]);
    }
}
