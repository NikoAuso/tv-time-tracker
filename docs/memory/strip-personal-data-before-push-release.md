---
name: strip-personal-data-before-push-release
description: "Prima di ogni push su GitHub o release/build distribuibile dell'app, togliere e oscurare i dati personali, poi ripristinarli in locale"
metadata: 
  node_type: memory
  type: feedback
  originSessionId: 3058a70b-1b85-4d9d-aeaa-56f870269519
---

Ogni push su GitHub o release/build distribuibile dell'app NON deve contenere i dati personali dell'utente: la libreria TV Time reale, il token TMDB, l'email.

**Why:** il repo è pubblico e gli APK sono distribuiti a terzi. `database/database.sqlite` e `database/seed.sqlite` contengono la libreria personale (~100 serie / ~500 film / cronologia); il token TMDB e l'email sono credenziali. Un leak resta per sempre nella cronologia git pubblica o dentro l'APK (estraibile). È già successo con gli screenshot `public/img/tmdb/step-3/4.png` (chiave API + email in chiaro) → serviti oscuramento + rewrite della history.

**How to apply:**
- **Push GitHub:** verificare che `database/*.sqlite` restino gitignored (mai `git add` forzato) e che nessun asset committato mostri token/email in chiaro (oscurare le zone, come fatto per gli screenshot della guida TMDB).
- **Release/build APK pubblico:** spostare via `database/seed.sqlite` prima di `native:build --release` (la migration `seed_library_from_bundle` fa `is_file($seed)` → assente = no-op → app vuota), poi **ripristinare il file** in locale per i build personali. `TMDB_TOKEN` è già escluso dai build via `cleanup_env_keys`.

Il flusso "build pubblico → poi riporta i dati in locale" è la norma. Vedi [[seed-sqlite-workflow]] e [[native-run-tty-workaround]].
