<?php

declare(strict_types=1);

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
        $admin = $demo['super_admin'] ?? [];

        User::factory()->create([
            'name' => $admin['username'] ?? 'Test User',
        ]);
    }
}
