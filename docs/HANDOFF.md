# HANDOFF — 2026-07-07

## Task in corso
**TvTimeTracker** (Laravel 13 + Livewire 4 + Flux + SQLite), app personale tipo TV Time, impacchettata come app **Android on-device** via NativePHP. Base MVP + packaging Android completi. Le sessioni 04-07/07 hanno aggiunto localizzazione italiana, branding custom, redesign Profilo, ricerca TMDB, preferiti, statistiche a tab (con generi), backup JSON completo, dettaglio film arricchito e vari fix. Resta il **build di release firmato** per il telefono fisico e il **push su GitHub**.

Export TV Time personale in `docs/Dati TV Time/` (gitignorato). Progetto in `~/Scrivania/PROGETTI/tv-time-tracker`.

## Stato
- Branch: `main`, ultimo commit `8b9a1df`. Nessun worktree. **⚠️ La history è stata RISCRITTA** (filter-branch) il 07/07 per l'anonimizzazione: gli hash dei commit precedenti sono cambiati; i riferimenti a hash vecchi in questo file sono obsoleti. Backup pre-rewrite in `scratchpad/`.
- **Import doppio formato (07/07)**: oltre allo zip GDPR (CSV), ora si importa anche l'export dell'estensione **«TV Time Out»** (JSON, id imdb/tvdb) via `import:tvtime-json`. `MovieMatcher` risolve il `tmdb_id` all'import (imdb→tmdb per JSON, titolo+anno per CSV) così i due formati **convergono** su un solo film. Tre import (GDPR, estensione, backup JSON) passano tutti per `x-dropzone` in **base64** (aggira `upload_max_filesize=2M` del PHP NativePHP); `livewire.php payload.max_size` alzato a 8MB. Colonna `movies.imdb_id` aggiunta. Rimossa la pagina **Aspetto** (resta il toggle in top-bar). Suite 118/118, PHPStan 0, Pint puliti.
- **Pronto per il primo push**: audit ok (nessun `.sqlite`/`.env`/segreto tracciato, nessun token/email in chiaro, `docs/*` e `worktrees/` gitignorati). Nessun remote configurato.
- **Token TMDB ora obbligatorio per-utente (07/07)**: rimosso il fallback al token globale. Middleware `RequireTmdbToken` (alias `tmdb`, applicato ai gruppi route tranne `import.edit`): senza token → redirect a `settings/import` (che mostra un banner "token obbligatorio"); con token → lo inietta in `config('services.tmdb.token')` per la richiesta. `UserFactory` ha ora un `tmdb_token` di default + state `withoutTmdbToken()`. `TMDB_TOKEN` aggiunto a `cleanup_env_keys` (mai impacchettato nei build di release). Il `.env` locale tiene il token solo per comodità dev (non più usato come fallback dall'app).
- **Anonimizzazione per GitHub (07/07)**: gli screenshot `public/img/tmdb/step-3/4.png` esponevano **API key TMDB reale + email** → oscurati e **originali purgati dalla history**. `run-emulator.sh` rimosso dalla history e ignorato (`/docs/*`), mantenuto in locale. Audit ok: `.env`/`.sqlite`/keystore non tracciati, nessun segreto nella cronologia, seeder generici (`Test User`), migration seed = no-op fuori dal device. **AZIONE UTENTE RESIDUA: rigenerare la API key + Read Access Token su TMDB** (la chiave esposta va considerata bruciata) e aggiornare `TMDB_TOKEN` nel `.env` locale.
- Repo **mai pushato** (nessun remote): il leak era solo locale. Pronto per il primo push dopo la rigenerazione della chiave.
- **Sessione 07/07 (seconda parte)**: pagina "Da guardare" → **"Serie da vedere"** con toggle lista/griglia, elementi che aprono l'episodio, feedback verde al click (`wire:loading` scoped). Scheda **episodio** arricchita: link alla serie, valutazione a stelle (nuova colonna `watched_episodes.rating`), sezione "Dove vederlo" (provider serie, cache condivisa). Commit fino a `ed19b08`. Suite 109/109. **Schema cambiato** (`watched_episodes.rating`): DB dev aggiornato con ALTER, **`seed.sqlite` rigenerato**.
- **Sessione 07/07 (questa)**: rimosso scaffolding **Fortify** inerte (pacchetto + provider + azioni + viste auth + helper test); lazy-load poster nelle griglie; empty state migliori (Libreria/Liste); feedback async (toast crea-lista + loading su "Segna visto"/"Salva nome"); UX residue (feedback touch su star-rating, grafico episodi/mese più leggibile). Commit `9284e59` → `aaf92b7`. Suite 106/106, PHPStan 0, Pint puliti. **`seed.sqlite` allineato**, `npm run build` rifatto (classi Tailwind nuove nel CSS).
- **Accessibilità bottoni-icona: già a posto** (ogni icon-only ha aria-label/testo) — verificato, niente da fare.
- **NON pushato** su GitHub (lo fa l'utente). Repo previsto pubblico. README + LICENSE MIT presenti.
- Working tree pulito.
- Suite/PHPStan/Pint: **da riverificare** — non rieseguiti dopo gli ultimi ~42 commit (all'ultimo check noto: 68/68 verde, PHPStan 0, Pint pulito; nel frattempo aggiunto `tests/Feature/UserDataTest.php`).
- **Schema cambiato** dopo lo handoff precedente (`create_shows`/`create_movies` toccate) → **rigenerare `database/seed.sqlite`** prima del prossimo build on-device.

## Funzionalità presenti (mappa dell'app)
Nav mobile a 5 tab: **Da guardare · Libreria · Cerca · Statistiche · Profilo** (le Liste sono nel Profilo).

- **Da guardare** (dashboard): primo episodio non visto per ogni serie seguita.
- **Libreria**: serie + film uniti, ricerca, filtri tipo (Tutti/Serie/Film) e stato (**Visti / In corso · Da vedere · Archiviate**, con badge conteggio leggibile anche da attivo).
- **Cerca** (`pages::search`): ricerca su **tutto TMDB** (risultati in italiano), tab Serie/Film, vista lista/griglia, miniatura a sinistra, pulsante **Aggiungi** → scarica in libreria come *watchlist* (serie con episodi via `TmdbLibrary`), oppure "In libreria".
- **Libreria**: le **serie** sono raggruppate in **Da iniziare / In corso / Concluse** in base agli episodi visti (non allo status), logica condivisa con le statistiche via `SeriesProgress`.
- **Statistiche**: due tab **Serie**/**Film**, ciascuno con più metriche (episodi/film, ore, seguite/watchlist, preferiti, voto medio, durata media film, grafico episodi per mese, serie più viste, film per decennio) + **ripartizione per genere** (`partials/genre-bars.blade.php`).
- **Backup**: **export/import JSON** di tutti i dati utente (`UserData`), con import irrobustito contro manomissioni del catalogo condiviso. Distinto dall'import zip GDPR di TV Time.
- **Dettaglio serie/film**: segna visto/watchlist, **rating a stelle**, **cuore preferito**, aggiunta a **liste**. Film con hero/genere/provider/trailer e modale rewatch/unwatch. Episodi serie in **accordion per stagione**.
- **Liste** (`lists`, `lists/{userList}`): indice crea/elimina, dettaglio con rimozione item + **elimina lista**; accessibili dal Profilo.
- **Profilo** (ex "Impostazioni", icona utente): header con avatar-logo + nome modificabile inline, **riepilogo tempo** (Tempo serie/film in mesi·giorni·ore), caroselli **Preferiti** (Serie/Film), sezione **Liste** (create/preview), sezione **Gestione** → sottopagine PIN / Importa dati / Aspetto (drill-down con "← Profilo").
- **Importa dati** (`settings/import`): upload `.zip` GDPR TV Time + sync TMDB post-import (schermata di caricamento). **Token TMDB per-utente** (cast encrypted) con modale-guida (5 step + screenshot in `public/img/tmdb/`).
- **PIN** locale opzionale con throttle. **Aspetto** (tema Flux). Nessun campo email (rimosso).

## Modello dati / stati
- `user_shows.status`: `following` (Visti/In corso) · `watchlist` (Da vedere) · `archived`. Colonne extra: `is_favorite`, `rating`, `followed_at`.
- `user_movies.status`: `watched` (Visti) · `watchlist` (Da vedere). Extra: `is_favorite`, `rating`, `rewatch_count`.
- `users.tmdb_token` (encrypted). `user_lists` + `list_items` (morph) per le liste.
- **L'import è un merge**: mai duplicati (firstOrCreate/updateOrCreate), mai cancella; aggiorna solo gli stati.

## ⚠️ Note operative / gotcha (leggere prima di lavorare)
1. **Dopo aver aggiunto/rimosso classi Tailwind nei blade: lanciare `npm run build` PRIMA di `php artisan native:run android`.** `native:run` NON rigenera il CSS, impacchetta `public/build` così com'è → le classi nuove mancherebbero (layout rotto). Vale anche in dev dopo modifiche a route/schema: `php artisan optimize:clear`.
2. **Migration pre-prod**: si modificano le `create_*` originali (no `alter_*`). Ma il `database.sqlite` dev ha i **dati reali** → NON fare `migrate:fresh`. Le colonne nuove si aggiungono al DB dev con `ALTER TABLE` (che le mette **in coda**, ordine diverso dalla migration).
3. **`BundleSeeder` ora copia per NOME colonna** (non `SELECT *`) → immune al disallineamento dell'ALTER. Fix di oggi (`2f75b1c`): prima i valori dopo la colonna ALTERata si spostavano on-device (rating prendeva `followed_at`, ecc.).
4. **Rigenerare `database/seed.sqlite`** dopo ogni cambio schema/dati prima di un build on-device: `cp database/database.sqlite database/seed.sqlite`. Attualmente allineato e **senza PIN** (un PIN di test residuo bloccava l'app; rimosso).
5. **Per vedere dati freschi on-device serve reinstallazione pulita**: `adb uninstall com.nikoauso.tvtimetracker` poi `native:run` (il solo `pm clear` non basta sempre). Riavvio emulatore: `./run-emulator.sh`.
6. **Il tuo IDE riformatta i `.blade.php` al salvataggio** in uno stile diverso dal resto (Pint non tocca i blade). Attenzione a non committare quel churn.

## In sospeso / punti aperti
- **Build di release firmato** (interattivo, lo lanci tu): `native:credentials` → `native:build --release`. Verificare app-icon (`public/icon.png` 1024² opaco) e dati italiani sul device fisico.
- **Push GitHub**: repo pronta; confermare licenza (MIT di default).
- **Play Store**: milestone a sé (de-personalizzare: niente seed nei build di release, onboarding con import zip + token per-utente già pronti, AAB firmato, Play Console, privacy policy).
- **Extra futuri**: notifiche nuovi episodi (NativePHP notifications + scheduling), pulizia dead-code Fortify più profonda (login view/auth layouts, wiring core).
- ✅ **Chiuso**: stato serie "Da iniziare / In corso / Concluse" (rilevamento automatico da episodi visti) — era il punto aperto "Concluso per le serie".

## Prossimo passo
Aree aperte concordate con l'utente. **Fatti**: Fortify + tutte le migliorie UX (empty state, lazy-load, feedback async, touch star-rating, grafico mesi). Scartati come YAGNI: paginazione griglia (risolta col lazy-load), virtualizzazione episodi (accordion carica una stagione per volta), empty-state statistiche (utente reale ha già dati). Restano:
- **Play Store readiness** (milestone): skip-seed nei build release, onboarding primo avvio (import zip + token TMDB già pronti nel Profilo), AAB firmato, privacy policy, data-safety form.
- **Operativi utente**: build di release firmato su device fisico, push GitHub.

Prima di ogni build on-device: `npm run build` + reinstallazione pulita (`seed.sqlite` già allineato).

## File chiave (nuovi/rilevanti)
- Ricerca: `pages/⚡search.blade.php`, `app/Services/TmdbLibrary.php`, `app/Services/Tmdb.php` (searchShows/searchMovies/getMovie, `language=it-IT`), `partials/poster.blade.php`
- Profilo: `pages/settings/⚡profile.blade.php` (tempo umano, preferiti, liste, gestione)
- Preferiti/rating/liste: `pages/⚡show.blade.php`, `⚡movie.blade.php`, `partials/star-rating.blade.php`, `add-to-list.blade.php`, `pages/⚡lists.blade.php`, `⚡list.blade.php`
- Statistiche: `pages/⚡stats.blade.php` (tab Serie/Film), `partials/genre-bars.blade.php`, `partials/stat-time-card.blade.php`
- Progresso serie / tempo: `app/Services/SeriesProgress.php` (bucket Da iniziare/In corso/Concluse, condiviso con la libreria), `app/Services/WatchTime.php`
- Backup JSON: `app/Services/UserData.php` (export/import), `tests/Feature/UserDataTest.php`
- Componenti UI: `components/{appearance-toggle,back-button,remove-button}.blade.php`, `partials/search-card.blade.php`
- Seed/on-device: `app/Services/BundleSeeder.php` (copia per nome), `config/nativephp.php`
- Branding: `components/app-logo-icon.blade.php`, `public/{favicon.svg,favicon.ico,apple-touch-icon.png,icon.png}`
- Import/token: `pages/settings/⚡import.blade.php`, `public/img/tmdb/step-*.png`

## Memoria progetto
`~/.claude/projects/-home-nikoauso-Scrivania-PROGETTI-tv-time-tracker/memory/` (se presente).
