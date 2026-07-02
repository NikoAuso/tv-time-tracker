<?php

namespace Database\Factories;

use App\Models\Show;
use App\Models\User;
use App\Models\UserShow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserShow>
 */
class UserShowFactory extends Factory
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
            'show_id' => Show::factory(),
            'status' => 'following',
            'is_favorite' => false,
        ];
    }
}
