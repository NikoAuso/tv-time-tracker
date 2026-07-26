<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // Username demo condiviso fra i progetti (fallback se il file non esiste).
        // Qui l'auth e a profilo + pin: email/password non esistono in questo progetto.
        $demo = @include dirname(base_path()).'/demo-credentials.php';
        $demo = is_array($demo) ? $demo : [];

        User::factory()->create([
            'name' => $demo['username'] ?? 'Test User',
        ]);
    }
}
