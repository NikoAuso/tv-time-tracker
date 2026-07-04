<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserList;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserList>
 */
class UserListFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->words(2, true),
        ];
    }
}
