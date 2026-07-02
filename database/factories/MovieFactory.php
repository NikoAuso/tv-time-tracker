<?php

namespace Database\Factories;

use App\Models\Movie;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Movie>
 */
class MovieFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->unique()->sentence(2),
            'release_date' => fake()->date(),
            'runtime' => fake()->numberBetween(80, 180),
            'tvtime_uuid' => fake()->unique()->uuid(),
        ];
    }
}
