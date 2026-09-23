---
name: on-device-migrations-on-update
description: "Su update dell'app NativePHP le migration girano (migrate --force) solo se cambia la versione nel .env; il DB persiste e i dati restano"
metadata: 
  node_type: memory
  type: project
  originSessionId: 88e680f3-f3fd-42d9-b835-7f3c0496f0b5
---

All'aggiornamento dell'APK il DB **non** si perde e le nuove migration girano da sole, ma solo a certe condizioni.

**Meccanismo (Android, `vendor/nativephp/mobile/.../LaravelEnvironment.kt`):** a ogni cold boot `initialize()` chiama `extractLaravelBundle()`, che confronta l'identita' composita `NATIVEPHP_APP_VERSION` + `NATIVEPHP_APP_VERSION_CODE` (dal `.env` imbarcato) con quella del bundle gia' estratto. Se diversa → ri-estrae il codice PHP nuovo e ritorna `didExtract=true`; **solo allora** `runBaseArtisanCommands()` lancia `optimize:clear`, `storage:link` e **`migrate --force`**. Se l'identita' coincide, salta tutto (nessun migrate).

**Conseguenze pratiche:**
- Il DB vive in `persisted_data/database/database.sqlite` nell'area dati dell'app: **non** viene sovrascritto dall'APK. `migrate --force` gira incrementale su quel DB → le migration append-only (`ADD COLUMN` nullable) aggiungono colonne preservando le righe. Coerente con la policy migration append-only del progetto.
- **Bisogna bumpare la versione** (`NATIVEPHP_APP_VERSION` o `_CODE` in `.env`) perche' un cambio schema arrivi on-device: senza bump l'identita' non cambia e `migrate` non parte. Il versionCode Android dell'APK e' auto-incrementato da NativePHP ma NON e' quello che conta qui — conta il valore nel `.env` (vedi [[android-build-needs-jdk17]]).
- Non viene mai eseguito `migrate:fresh` in update: nessun rischio di wipe automatico.
- Colonne nuove nascono a `NULL`; per popolarle (es. `providers` del filtro piattaforma) servono visite alle schede o il pulsante "Risincronizza da TMDB" in Impostazioni.

Correlati: [[strip-personal-data-before-push-release]], [[seed-sqlite-workflow]].
