# TvTimeTracker

Stack e funzionalità: vedi `README.md`. Qui solo i vincoli non deducibili dal codice.

## App on-device

Gira come app Android via NativePHP: PHP e SQLite viaggiano dentro l'APK, **non c'è nessun server**.

- Niente queue worker, cache Redis, cron o storage remoto: tutto ciò che presuppone un processo esterno all'app non è disponibile.
- SQLite è l'unico database.
- Build ed esecuzione con i comandi `native:*` (`native:run android`, `native:build --release`), non `artisan serve`.
- La cartella `nativephp/` è generata da `native:install` ed è gitignorata: non modificarne il contenuto a mano, le modifiche verrebbero perse.

## Migration: append-only

L'app è installata su device con dati reali (libreria importata da TV Time).

- Mai modificare una migration già eseguita. Ogni cambio schema è una nuova migration.
- Modificando una colonna, ripetere **tutti** gli attributi già presenti: Laravel droppa quelli omessi.
- Mai `migrate:fresh` come strategia di validazione.

## Test

Pest, non PHPUnit.

- `composer test` esegue in sequenza pint, phpstan e la suite: usarlo a fine task.
- Durante il lavoro, test mirati con `php artisan test --filter=...`.

## TMDB

Il token TMDB è **per-utente**, salvato encrypted sulla colonna `users.tmdb_token`. Non è una variabile d'ambiente: non cercarlo né aggiungerlo in `.env`.
