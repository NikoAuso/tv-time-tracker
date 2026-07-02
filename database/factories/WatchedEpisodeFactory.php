<?php

namespace Database\Factories;

use App\Models\Episode;
use App\Models\User;
use App\Models\WatchedEpisode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WatchedEpisode>
 */
class WatchedEpisodeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'episode_id' => Episode::factory(),
            'watched_at' => now(),
        ];
    }
}
