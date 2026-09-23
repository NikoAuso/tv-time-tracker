---
name: tvtimetracker-project
description: "TvTimeTracker — app personale tipo TV Time, obiettivo mobile Android via NativePHP, stack e decisioni chiave"
metadata: 
  node_type: memory
  type: project
  originSessionId: e5283aea-814e-4ca1-8748-a42650ae5813
---

Progetto **TvTimeTracker** in `~/Scrivania/PROGETTI/TvTimeTracker` (repo git proprio). Ricrea TV Time per **uso personale**, partendo dall'export GDPR in `~/Scrivania/Dati TV Time` (34 CSV, user_id 84073318).

**Obiettivo finale:** app **mobile Android** che gira on-device senza hosting, impacchettata con **NativePHP for Mobile v3** (gratis/MIT da feb 2026, richiede Android Studio + JDK 17, install via ADB, nessuno store). iOS scartato (servirebbe un Mac). Flusso: sviluppo/seed su desktop → si imbarca un SQLite già pieno nell'app.

**Stack:** Laravel 13 (starter kit Livewire, quindi Flux + Fortify) · Livewire 4 · **SQLite** · TMDB API per il catalogo.

**Decisioni non ovvie:**
- Gli `s_id`/`tv_show_id` nell'export sono **ID TheTVDB**, non TMDB. Per arricchire da TMDB serve l'endpoint `/find/{id}?external_source=tvdb_id`.
- TMDB: usare l'**API Read Access Token v4**, non la key v3. Ancora da fornire (serve per `shows:sync`).
- Migration policy: progetto greenfield → si editano le `create_*` e si valida con `migrate:fresh`.

**Fatto (step 1-4), su branch `feature/mvp-foundation` (3 commit, non ancora merged su main):** scaffold; 4 tabelle/model (`shows`,`episodes`,`user_shows`,`watched_episodes`); comando `import:tvtime {path}` (idempotente, testato); UI Livewire 4 single-file — Libreria (`/library`) e Dettaglio serie (`/shows/{show}`, toggle visto + aggiunta manuale), voce sidebar "Libreria". Import reale: **99 serie, 3262 episodi, 3262 visti**. Utente creato dall'import: `me@tvtime.local` / `password`. Suite 38/38. Avvio: `composer run dev`.

**Nota UI:** `episodes` contiene solo gli episodi già visti (l'import legge le righe `watch-episode`), quindi il "Da guardare"/next-up NON è calcolabile finché `shows:sync` (TMDB) non scarica l'elenco completo episodi.

**Fatto anche (step 5-6):** pagina Statistiche (`/stats`, card + grafico episodi/mese + top 10, dai dati watched log — nota: 2024-09 ha ~1266 per il backlog importato); client `App\Services\Tmdb` + comando `shows:sync` (risolve TVDB→TMDB, salva poster/overview/status/total_episodes, upsert elenco completo episodi con runtime), testati con `Http::fake`. Config `services.tmdb.token` da env `TMDB_TOKEN` (già in .env/.env.example, ancora vuoto).

**Sync eseguito** (token v4 in `.env`, gitignorato): 98/99 serie con poster, **5378 episodi totali** (3262 visti → ~2116 da vedere). Unica non risolta su TMDB: "R.I.P. – Roast In Peace" (tvdb 469048). Dashboard **"Da guardare"** (`/dashboard`, route ora Livewire `pages::dashboard`) mostra il primo episodio non visto e già uscito per ogni serie seguita, con bottone "Visto" che avanza; sui dati reali 33 serie hanno un next-up. Sidebar: "Da guardare" (play), "Libreria" (film), "Statistiche" (chart-bar).

**Film (Fase 1) fatta:** model `Movie`/`UserMovie` (status watched/watchlist), `import:tvtime` esteso a `tracking-prod-records.csv` (530 film: 502 visti, 28 watchlist; runtime secondi→minuti; dedup per uuid TV Time), comando `movies:sync` (match TMDB per titolo+anno; 512/530 risolti). Libreria **unificata** (filtri Tipo Tutti/Serie/Film + Stato con umbrella "In libreria"=serie following+film visti), card condivisa `partials/library-card`. Stats con Film visti + Ore totali (**3021 ore**: 2436 serie + 584 film). Suite 49/49, 8 commit su `feature/mvp-foundation`.

**IMPORTANTE workflow dati:** l'ordine corretto è `migrate:fresh` → `import:tvtime` → `shows:sync` → `movies:sync`. Se rifai `migrate:fresh`+import devi ri-lanciare ENTRAMBI i sync, altrimenti le serie perdono poster/runtime/elenco episodi completo.

**Target chiarito:** app personale on-device, ma **codice pubblico su GitHub** → sicurezza = igiene repo (mai committare `.env`, `*.sqlite`, l'export personale; servono README+LICENSE; audit pre-push). SEO e multi-utente fuori scope.

**Login → sblocco locale (deciso):** niente email/password (attrito inutile per single-user on-device). Il codice usa `Auth::id()` ovunque quindi si tiene UN utente auto-loggato all'avvio + lucchetto opzionale PIN/biometria (biometria via plugin NativePHP). Rimozione login/registrazione/reset/passkey/2FA/security = parte della Fase 3 (Pulizia).

**Fase 2 fatta:** episodi ora hanno `overview`+`still_path` (colonne aggiunte editando la migration create_episodes; popolati da `shows:sync`). Dettaglio serie rifatto: lista episodi con titoli/date, progress per stagione, **"Segna stagione"** e **"fino a qui"** (bulk via `WatchedEpisode::insert`). Nuova **scheda episodio** (`/episodes/{episode}`, `pages::episode`) con still, trama, runtime, data vista, toggle. Suite 53/53, 10 commit.

**Fase 3 fatta (auth rework + pulizia):** rimossi login email/password, registrazione, reset, verifica email, 2FA, passkey (feature Fortify svuotate, viste/migration/trait/factory/test relativi eliminati). Middleware `AutoLoginSingleUser` (web, append) auto-logga l'unico utente → nessuna schermata login. **PIN locale opzionale**: colonna `users.pin` (cast hashed), middleware alias `pin` (`RequirePinUnlock`) su tutte le rotte app, schermata `/unlock` (`pages::unlock`), gestione in `settings/pin` (`pages::settings.pin`), azione `POST /lock` + voce menu "Blocca" (solo se PIN attivo). `/` redirect a `/dashboard`. Suite 31/31 (calata da 53 per i test rimossi). Smoke HTTP: `/`→dashboard, `/login`→redirect dashboard, pagine 200.

**Ceiling noto:** restano dead-code innocui (rotte Fortify `login`/`logout`, `login.blade.php`, `app/Actions/Fortify/*`); mai raggiunti grazie all'auto-login. Ripulibili in futuro. Biometria mobile = plugin NativePHP (dopo).

**Fase 4 fatta (UI mobile):** bottom tab bar fissa su mobile (Da guardare/Libreria/Statistiche/Impostazioni, icone dinamiche via `x-dynamic-component 'flux::icon.'.$icon`, safe-area inset), sidebar solo su desktop (`max-lg:hidden`), top bar mobile slim con logo + lock. Rebrand starter kit → "TV Time" (app-logo + `APP_NAME=TvTimeTracker`), rimossi link Repository/Documentation. Verifica visiva reale con Chrome DevTools (viewport 390×844): dashboard e libreria confermate poster-centriche e app-like. Suite 31/31.

**Fase 5 fatta (packaging Android, nucleo funzionante su emulatore):** `nativephp/mobile` 3.3.6 su Laravel 13/PHP 8.5 (nessun conflitto, gratis, nessuna licenza). `native:install android` → progetto in `nativephp/android/` (gitignored dal suo `.gitignore`). `NATIVEPHP_APP_ID=com.nikoauso.tvtimetracker`. App gira on-device (PHP 8.5.7 + Laravel 13 dentro Android). 6 commit su `feature/mvp-foundation`.

**Seed on-device (deciso + fatto):** NativePHP ignora `database/database.sqlite` (escluso in `BundleExclusions::PROJECT`) e ricostruisce il DB in `app_storage/persisted_data/` via migration a ogni boot → parte vuoto. Soluzione: `database/seed.sqlite` (copia del DB pieno, gitignored da `database/.gitignore *.sqlite*`, NON committato per privacy) imbarcato nel bundle; `App\Services\BundleSeeder` fa `ATTACH` + `INSERT ... SELECT` + riallinea `sqlite_sequence`; migration `seed_library_from_bundle` la esegue solo `BundleSeeder::runningOnDevice()` (= `nativephp_call` è funzione **interna** dell'estensione, non il fallback userland → `ReflectionFunction::isInternal()`; distingue device da dev/test perché APP_ENV=local in entrambi). Test in `DatabaseMigrations`-style con connessione sqlite su file (ATTACH/DETACH vietati in transazione RefreshDatabase). Suite 33/33.

**Trappola view cache (RISOLTA):** NativePHP scrive le view Blade compilate in `persisted_data`, che **sopravvive ai reinstall** → le modifiche UI non comparivano (usava le compilate stale). Fix: `storage/framework/views/*.php` aggiunto a `cleanup_exclude_files` in `config/nativephp.php`. Regola generale: se una modifica UI non appare on-device, è la view compilata stale in persisted_data.

**Emulatore su Wayland+NVIDIA (RISOLTO):** con `-gpu host` il device resta "offline" al boot; serve `QT_QPA_PLATFORM=xcb` (forza XWayland) → accelerazione hardware, niente ANR/lag. Config permanente: `~/.android/avd/Pixel_7.avd/config.ini` con `hw.gpu.mode=host` + `hw.ramSize=4096`; script `run-emulator.sh` nel repo (`QT_QPA_PLATFORM=xcb emulator -avd Pixel_7 -gpu host -memory 4096`). AVD è google_apis_playstore (no `adb root`, ma `run-as` ok su build debug). Env `ANDROID_HOME`/`JAVA_HOME` (JBR 21 di Android Studio Toolbox) nel `~/.bashrc`. Build via `php artisan native:run android` (da terminale vero per il TTY; in background serve wrapper `script -qfec`).

**UI mobile bottom nav (sistemata Fase 5):** safe-area via `viewport-fit=cover` nel meta (senza, `env(safe-area-inset-*)` = 0); top bar `pt-[env(safe-area-inset-top)]`; nav = `grid grid-cols-4` con `<a>` semplici (NON `flux:link`, che impone layout interno e sfasa icona/testo) + `pb-[max(env(safe-area-inset-bottom),_1.25rem)]` (inset bottom ~0 su questa WebView).

**Backlog residuo (ordine):** build **release firmato** (`native:build --release`) per telefono fisico (finora solo debug su emulatore). Extra futuri: **import da ZIP dell'export TV Time** (upload del .zip GDPR self-service → estrazione via ZipArchive in dir temp → riuso della logica `import:tvtime`; su mobile con upload Livewire nei settings, così non serve scompattare a mano), rating, liste, notifiche nuovi episodi, toggle/aggiunta film interattiva (libreria film ora sola lettura), pulizia dead-code Fortify, audit repo pre-push GitHub (README + LICENSE).

Convenzioni starter kit: model con `#[Fillable]` + `casts()` + PHPDoc; componenti Flux UI; page component single-file con prefisso `⚡` in `resources/views/pages`, rotte via `Route::livewire('path','pages::name')`.
