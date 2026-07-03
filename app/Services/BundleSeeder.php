<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use ReflectionFunction;

/**
 * Popola il database on-device dal file `database/seed.sqlite` imbarcato nel
 * bundle NativePHP. NativePHP ignora il .sqlite del progetto e ricostruisce lo
 * schema via migration a ogni avvio, quindi il DB parte vuoto: qui copiamo i
 * dati reali una volta sola, al primo boot dell'app installata.
 */
class BundleSeeder
{
    /** @var list<string> Tabelle di dominio copiate, in ordine di dipendenza FK. */
    public const TABLES = [
        'users',
        'shows',
        'episodes',
        'movies',
        'user_shows',
        'user_movies',
        'watched_episodes',
    ];

    /**
     * Copia i dati dal seed nella connessione di default. Le FK sono disattivate
     * durante la copia per non dipendere dall'ordine di inserimento; le sequenze
     * autoincrement vengono riallineate al MAX(id) copiato.
     */
    public static function seedInto(string $seedPath, ?string $connection = null): void
    {
        $db = DB::connection($connection);

        $db->statement('PRAGMA foreign_keys = OFF');
        $db->statement("ATTACH DATABASE '{$seedPath}' AS seed");

        foreach (self::TABLES as $table) {
            $db->statement("INSERT INTO {$table} SELECT * FROM seed.{$table}");
            $db->statement("UPDATE sqlite_sequence SET seq = (SELECT MAX(id) FROM {$table}) WHERE name = '{$table}'");
        }

        $db->statement('DETACH DATABASE seed');
        $db->statement('PRAGMA foreign_keys = ON');
    }

    /**
     * True solo quando gira dentro il runtime NativePHP on-device: lì
     * `nativephp_call` è una funzione interna dell'estensione, mentre in
     * dev/test è il fallback userland di jump_bridge_functions.php.
     */
    public static function runningOnDevice(): bool
    {
        return function_exists('nativephp_call')
            && (new ReflectionFunction('nativephp_call'))->isInternal();
    }
}
