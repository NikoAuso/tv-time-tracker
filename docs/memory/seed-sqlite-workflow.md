---
name: seed-sqlite-workflow
description: "L'app si semina da database/seed.sqlite, non dai CSV — va rigenerato dopo modifiche ai dati"
metadata: 
  node_type: memory
  type: project
  originSessionId: 42e11ef8-cde6-4315-a75f-a2a523bf7d58
---

L'app **non** legge i CSV di TV Time a runtime: quelli servirono solo all'import iniziale una-tantum. A ogni build/fresh, `App\Services\BundleSeeder::seedInto()` (via la migration `*_seed_library_from_bundle.php`) copia le tabelle da **`database/seed.sqlite`** dentro il DB.

**Quindi:** dopo qualsiasi correzione ai dati (re-point TMDB di film/serie, backfill, merge, rewatch, trame i18n) fatta su `database/database.sqlite`, rigenerare il seed prima della prossima build on-device:

```bash
cp database/database.sqlite database/seed.sqlite
# il token TMDB è cifrato (cast encrypted con APP_KEY): NON imbarcarlo nel seed,
# altrimenti on-device dà "The MAC is invalid" (l'APP_KEY del build può differire).
sqlite3 database/seed.sqlite "UPDATE users SET tmdb_token = NULL;"
```

On-device l'utente reinserisce il token via il gate (RequireTmdbToken → pagina Token), così viene cifrato con l'APP_KEY del device. `users.tmdb_token` è l'unico campo cifrato nel seed; `pin` è hashed (nessun problema).

Modificare i CSV in `docs/Dati TV Time/` è inutile (nessuno li rilegge) e da evitare (export GDPR grezzo). Se un domani servisse un re-import auto-corretto, la via è una mappa di override nel repo (`tvtime_uuid → tmdb_id`), non toccare i CSV.

Correlati: [[workflow-solo-main]].
