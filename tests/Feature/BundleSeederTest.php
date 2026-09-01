<?php

declare(strict_types=1);

use App\Services\BundleSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Crea un file SQLite con lo schema minimo delle tabelle di dominio
 * (solo la colonna id autoincrement, più name per users).
 */
function makeSqliteSchema(string $path): void
{
    $pdo = new PDO('sqlite:'.$path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
    foreach (array_slice(BundleSeeder::TABLES, 1) as $table) {
        $pdo->exec("CREATE TABLE {$table} (id INTEGER PRIMARY KEY AUTOINCREMENT)");
    }
}

it('copia i dati dal seed nella connessione target', function () {
    $main = sys_get_temp_dir().'/main_'.uniqid().'.sqlite';
    $seed = sys_get_temp_dir().'/seed_'.uniqid().'.sqlite';
    makeSqliteSchema($main);
    makeSqliteSchema($seed);

    $seedPdo = new PDO('sqlite:'.$seed);
    $seedPdo->exec("INSERT INTO users (id, name) VALUES (5, 'Me')");
    $seedPdo->exec('INSERT INTO user_lists (id) VALUES (1)');
    $seedPdo->exec('INSERT INTO list_items (id) VALUES (1), (2)');

    config(['database.connections.bundle_test' => [
        'driver' => 'sqlite', 'database' => $main, 'prefix' => '', 'foreign_key_constraints' => true,
    ]]);
    DB::purge('bundle_test');

    BundleSeeder::seedInto($seed, 'bundle_test');
    $conn = DB::connection('bundle_test');

    expect($conn->table('users')->where('name', 'Me')->count())->toBe(1);

    // le liste personalizzate seguono la libreria: senza, il seed le lascerebbe indietro
    expect($conn->table('user_lists')->count())->toBe(1)
        ->and($conn->table('list_items')->count())->toBe(2);

    // sequenza riallineata al MAX(id) copiato: il prossimo insert è 6, non 1
    $conn->table('users')->insert(['name' => 'Next']);
    expect($conn->table('users')->where('name', 'Next')->value('id'))->toBe(6);

    foreach ([$main, $seed] as $path) {
        @unlink($path);
    }
});

it('non seeda fuori dal runtime on-device', function () {
    expect(BundleSeeder::runningOnDevice())->toBeFalse();
});
