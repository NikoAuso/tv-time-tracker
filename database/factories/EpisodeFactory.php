<?php

namespace Database\Factories;

use App\Models\Episode;
use App\Models\Show;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Episode>
 */
class EpisodeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'show_id' => Show::factory(),
            'season_number' => 1,
            'episode_number' => fake()->numberBetween(1, 24),
        ];
    }
}
