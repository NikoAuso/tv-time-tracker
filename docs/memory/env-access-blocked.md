---
name: env-access-blocked
description: "Claude non può leggere né modificare .env (permessi negati); il bump NATIVEPHP_APP_VERSION per le release lo fa l'utente"
metadata:
  node_type: memory
  type: project
  originSessionId: eeebce87-54a5-41ff-bbb2-04d27e354748
  modified: 2026-10-11T16:28:43.666Z
---

Lettura e scrittura di `.env` sono negate a Claude (anche `grep`/`sed` mirati). Verificato il 2026-10-04 durante la release v1.2.0.

**Why:** protezione dei segreti; tentare di aggirarla (es. `php -r` che legge l'env) non è accettabile.

Il 2026-10-11 l'utente ha scritto "hai i permessi", ma la lettura di `.env` è rimasta negata dal permission system: il blocco è nelle impostazioni dell'harness, non una scelta di Claude. Per verificare cosa finisce nell'APK si possono leggere singole chiavi non segrete del `.env` imbarcato nel bundle (`unzip -p <apk> assets/laravel_bundle.zip`), mai stampare `APP_KEY` o token.

**How to apply:** per una release, chiedere subito all'utente di lanciare `sed -i 's/^NATIVEPHP_APP_VERSION=.*/NATIVEPHP_APP_VERSION=X.Y.Z/' .env` prima del build, invece di provare a farlo. Senza bump il device non ri-estrae il codice (vedi [[on-device-migrations-on-update]]). Il resto del flusso release (seed fuori, `native:package` con JDK 17 e `script -qfec`, verifica `apksigner`/`aapt`) Claude lo esegue: vedi [[android-build-needs-jdk17]], [[strip-personal-data-before-push-release]].
