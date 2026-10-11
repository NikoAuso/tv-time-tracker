---
name: env-access-blocked
description: "Claude non può leggere né modificare .env (permessi negati); il bump NATIVEPHP_APP_VERSION per le release lo fa l'utente"
metadata:
  node_type: memory
  type: project
  originSessionId: eeebce87-54a5-41ff-bbb2-04d27e354748
  modified: 2026-10-04T15:01:35.747Z
---

Lettura e scrittura di `.env` sono negate a Claude (anche `grep`/`sed` mirati). Verificato il 2026-10-04 durante la release v1.2.0.

**Why:** protezione dei segreti; tentare di aggirarla (es. `php -r` che legge l'env) non è accettabile.

**How to apply:** per una release, chiedere subito all'utente di lanciare `sed -i 's/^NATIVEPHP_APP_VERSION=.*/NATIVEPHP_APP_VERSION=X.Y.Z/' .env` prima del build, invece di provare a farlo. Senza bump il device non ri-estrae il codice (vedi [[on-device-migrations-on-update]]). Il resto del flusso release (seed fuori, `native:package` con JDK 17 e `script -qfec`, verifica `apksigner`/`aapt`) Claude lo esegue: vedi [[android-build-needs-jdk17]], [[strip-personal-data-before-push-release]].
