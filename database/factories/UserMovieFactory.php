<?php

namespace Database\Factories;

use App\Models\Movie;
use App\Models\User;
use App\Models\UserMovie;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserMovie>
 */
class UserMovieFactory extends Factory
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
            'movie_id' => Movie::factory(),
            'status' => 'watched',
            'watched_at' => now(),
            'rewatch_count' => 0,
        ];
    }
}
