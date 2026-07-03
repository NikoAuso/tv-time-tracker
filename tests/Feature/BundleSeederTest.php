<?php

use App\Services\BundleSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Crea un file SQLite con lo schema minimo delle tabelle di dominio
 * (solo la colonna id autoincrement, più email per users).
 */
function makeSqliteSchema(string $path): void
{
    $pdo = new PDO('sqlite:'.$path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT)');
    foreach (array_slice(BundleSeeder::TABLES, 1) as $table) {
        $pdo->exec("CREATE TABLE {$table} (id INTEGER PRIMARY KEY AUTOINCREMENT)");
    }
}

it('copia i dati dal seed nella connessione target', function () {
    $main = sys_get_temp_dir().'/main_'.uniqid().'.sqlite';
    $seed = sys_get_temp_dir().'/seed_'.uniqid().'.sqlite';
    makeSqliteSchema($main);
    makeSqliteSchema($seed);

    (new PDO('sqlite:'.$seed))->exec("INSERT INTO users (id, email) VALUES (5, 'me@tvtime.local')");

    config(['database.connections.bundle_test' => [
        'driver' => 'sqlite', 'database' => $main, 'prefix' => '', 'foreign_key_constraints' => true,
    ]]);
    DB::purge('bundle_test');

    BundleSeeder::seedInto($seed, 'bundle_test');
    $conn = DB::connection('bundle_test');

    expect($conn->table('users')->where('email', 'me@tvtime.local')->count())->toBe(1);

    // sequenza riallineata al MAX(id) copiato: il prossimo insert è 6, non 1
    $conn->table('users')->insert(['email' => 'next@tvtime.local']);
    expect($conn->table('users')->where('email', 'next@tvtime.local')->value('id'))->toBe(6);

    foreach ([$main, $seed] as $path) {
        @unlink($path);
    }
});

it('non seeda fuori dal runtime on-device', function () {
    expect(BundleSeeder::runningOnDevice())->toBeFalse();
});
