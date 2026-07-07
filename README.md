# TvTimeTracker

![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.3+-777BB4?logo=php&logoColor=white)
![NativePHP](https://img.shields.io/badge/NativePHP-Android-05A6A6)
![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)

App personale in stile [TV Time](https://www.tvtime.com/) per tenere traccia di serie viste, episodi in sospeso e film, con statistiche. Gira come app **Android on-device** (nessun server): PHP e il database SQLite viaggiano dentro l'app tramite [NativePHP](https://nativephp.com/).

Progetto single-user pensato per uso proprio. Nasce perché **TV Time chiude il 15 luglio 2026** e cancella i dati degli utenti: questo strumento li importa e permette di continuare a tracciare la propria libreria. Ogni utente usa il proprio token TMDB personale.

## Screenshot

<!-- Aggiungi qui 2-3 screenshot dall'emulatore, es.:
![Serie da vedere](docs/img/serie-da-vedere.png)
![Scheda serie](docs/img/scheda-serie.png)
![Statistiche](docs/img/statistiche.png)
-->

## Stack

- **Laravel 13** (PHP 8.3+)
- **Livewire 4** + **Flux** per la UI
- **SQLite** come unico database
- **NativePHP Mobile** per il packaging Android
- **Tailwind CSS** + **Vite**
- Metadati serie/film/episodi da [TMDB](https://www.themoviedb.org/) (token per-utente)

## Funzionalità

- **Serie da vedere**: primo episodio non visto per ogni serie seguita, in vista lista o griglia
- **Libreria** unificata di serie e film con ricerca e filtri; le serie sono raggruppate in *Da iniziare / In corso / Concluse*
- **Ricerca** su tutto il catalogo TMDB (serie e film), in vista lista o griglia
- Segna visto per **episodio**, per **stagione**, "fino a qui" o l'intera serie
- **Voti a stelle** e **preferiti** per serie, film ed episodi
- **Liste** personalizzate per organizzare serie e film
- **Scheda episodio** con link alla serie, valutazione e "dove vederlo"
- Provider **streaming** ("dove vederlo") e **trailer** su serie e film
- **Statistiche**: episodi/film visti, ore totali, andamento per mese, serie più viste, film per decennio, ripartizione per genere
- **Import da TV Time** in due formati (export GDPR e estensione), **backup** JSON export/import
- **Tema** chiaro/scuro
- Blocco locale opzionale con **PIN** (con throttle anti brute-force)

## Setup (sviluppo)

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate

npm run dev          # asset in watch
php artisan serve    # http://localhost:8000
```

Al primo avvio l'app crea e autentica in automatico l'unico utente locale (vedi `AutoLoginSingleUser`), quindi ti porta a inserire il **token TMDB** (vedi sotto). Il PIN è opzionale e si imposta dalle impostazioni.

> Chi clona il repo parte con un'app **vuota**: il `database/seed.sqlite` con i dati personali non è versionato. Inserisci il tuo token TMDB e importa i tuoi dati.

## Token TMDB (obbligatorio, per-utente)

I metadati (poster, trame, episodi) arrivano da TMDB e ogni utente usa il **proprio** token, che si inserisce dall'app in **Profilo → Token TMDB**, dove c'è una guida passo-passo. Senza token l'app non è utilizzabile e reindirizza alla schermata di inserimento.

Il token è salvato cifrato nel database locale (`users.tmdb_token`), non è mai condiviso né imbarcato nei build (`TMDB_TOKEN` è escluso via `cleanup_env_keys`). In sviluppo puoi opzionalmente valorizzare `TMDB_TOKEN` nel `.env` per comodità, ma non è il meccanismo usato dall'app.

## Importare i propri dati da TV Time

Si importa da **Profilo → Importa / Esporta dati**, in due formati (entrambi idempotenti: reimportare non duplica, i campi mancanti si completano). Convergono sullo stesso record TMDB, quindi puoi usarli anche insieme.

| Formato | Come ottenerlo | Note |
|---|---|---|
| **Export GDPR** (CSV) | [gdpr.tvtime.com/gdpr/self-service](https://gdpr.tvtime.com/gdpr/self-service) — accedi e attendi che prepari lo zip | Ufficiale e completo (watchlist, liste, voti). Film matchati per titolo+anno. |
| **Estensione «TV Time Out»** (JSON) | [Chrome Web Store](https://chromewebstore.google.com/detail/tv-time-out-by-refract/pmejpdpjbkjklfceogdkolmgclldogbi) — esporta in ZIP mentre sei connesso a TV Time | Fornisce gli **id imdb/tvdb** → match TMDB esatto. Più affidabile per i visti. |

Import da CLI (sviluppo):

```bash
# export GDPR (cartella con i CSV)
php artisan import:tvtime /percorso/export --user=1
# export estensione (cartella con i JSON)
php artisan import:tvtime-json /percorso/export --user=1

php artisan shows:sync     # collega le serie a TMDB e scarica gli episodi
php artisan movies:sync    # collega i film a TMDB
```

I comandi `*:sync` usano il token TMDB dell'utente.

## Build Android on-device

Per un build personale il proprio DB viene imbarcato come `database/seed.sqlite` e copiato nel DB dell'app al primo avvio (vedi `BundleSeeder`). Per rigenerarlo dopo modifiche a dati o schema:

```bash
cp database/database.sqlite database/seed.sqlite
# il token TMDB è cifrato con l'APP_KEY: NON imbarcarlo nel seed, altrimenti
# on-device dà "The MAC is invalid". Lo reinserisci in-app al primo avvio.
sqlite3 database/seed.sqlite "UPDATE users SET tmdb_token = NULL;"
```

Build ed esecuzione tramite i comandi `native:*` di NativePHP:

```bash
php artisan native:run android          # debug su emulatore/device
php artisan native:credentials          # genera il keystore per la firma
php artisan native:build --release       # APK firmato
```

I build di release non imbarcano `TMDB_TOKEN` (escluso via `cleanup_env_keys`): ogni utente inserisce il proprio.

## Test

```bash
php artisan test
```

## Licenza

[MIT](LICENSE)
