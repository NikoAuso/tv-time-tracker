# TvTimeTracker

App personale in stile [TV Time](https://www.tvtime.com/) per tenere traccia di serie viste, episodi in sospeso e film, con statistiche. Gira come app **Android on-device** (nessun server): PHP e il database SQLite viaggiano dentro l'app tramite [NativePHP](https://nativephp.com/).

Progetto single-user pensato per uso proprio, con importazione dei dati dal proprio export GDPR di TV Time.

## Stack

- **Laravel 13** (PHP 8.3+)
- **Livewire 4** + **Flux** per la UI
- **SQLite** come unico database
- **NativePHP Mobile** per il packaging Android
- **Tailwind CSS** + **Vite**
- Metadati serie/film/episodi da [TMDB](https://www.themoviedb.org/)

## Funzionalità

- Dashboard "Da guardare": primo episodio non visto per ogni serie seguita
- Libreria unificata di serie e film, con ricerca e filtri per stato
- Segna visto per episodio, per stagione o "fino a qui"
- Statistiche: episodi/film visti, ore totali, andamento per mese, serie più viste
- Blocco locale opzionale con **PIN** (con throttle anti brute-force)

## Setup (sviluppo)

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate

# metadati da TMDB: serve un Read Access Token (v4) in TMDB_TOKEN nel .env
npm run dev          # asset in watch
php artisan serve    # http://localhost:8000
```

Al primo avvio, in assenza di un utente, l'app crea/usa l'unico utente locale e lo autentica in automatico (vedi `AutoLoginSingleUser`). Il PIN è opzionale e si imposta dalle impostazioni.

## Importare i propri dati da TV Time

Richiedi l'export GDPR dei tuoi dati a TV Time, poi:

```bash
# 'path' è la cartella che contiene i CSV dell'export
php artisan import:tvtime /percorso/export --user=1
php artisan shows:sync     # collega le serie a TMDB e scarica gli episodi
php artisan movies:sync    # collega i film a TMDB
```

## Build Android on-device

Il DB pieno viene imbarcato nel bundle come `database/seed.sqlite` e copiato nel DB dell'app al primo avvio (vedi `BundleSeeder`). Per rigenerarlo dopo un `migrate:fresh`:

```bash
php artisan import:tvtime ... && php artisan shows:sync && php artisan movies:sync
cp database/database.sqlite database/seed.sqlite
```

Build ed esecuzione tramite i comandi `native:*` di NativePHP:

```bash
php artisan native:run android          # debug su emulatore/device
php artisan native:credentials          # genera il keystore per la firma
php artisan native:build --release       # APK firmato
```

## Test

```bash
php artisan test
```

## Licenza

[MIT](LICENSE)
