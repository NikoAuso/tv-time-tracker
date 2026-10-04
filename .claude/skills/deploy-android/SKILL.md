---
name: deploy-android
description: Deploy dell'app su emulatore/device Android o build release firmato, con i preflight NativePHP del progetto (patch file chooser, strip dati personali, seed aggiornato). Usare quando si chiede di installare/provare l'app su Android o di preparare una release APK.
disable-model-invocation: true
---

# Deploy Android (NativePHP)

Orchestra gli script in `docs/` e i preflight che gli script non fanno.
Tutti i comandi vanno lanciati dalla root del progetto. Package id: `com.nikoauso.tvtimetracker`.

## 1. Che tipo di deploy?

- **Dev (emulatore/device, con i tuoi dati)** -> sezione 2.
- **Release (APK pubblico firmato, SENZA dati personali)** -> sezione 3.

## 2. Deploy dev

Preflight:

1. **Emulatore avviato?** Se no: `./docs/run-emulator.sh`.
2. **Hai appena rilanciato `native:install`?** La cartella `nativephp/` e' rigenerata e perde la patch del file chooser (`<input type=file>` non apre nulla in-app). Riapplicala:
   ```bash
   ./docs/patch-android-filechooser.sh
   ```
   E' idempotente: se gia' applicata, no-op.
3. **Hai modificato dati in `database/database.sqlite`?** Rigenera il seed (l'app si semina da `database/seed.sqlite`, non dai CSV):
   ```bash
   cp database/database.sqlite database/seed.sqlite
   sqlite3 database/seed.sqlite "UPDATE users SET tmdb_token = NULL;"
   ```
   Il token TMDB e' cifrato con l'APP_KEY: non imbarcarlo, altrimenti on-device da' "The MAC is invalid".

Deploy (gestisce JDK 17/21, build asset, reinstallazione pulita, pseudo-TTY per `native:run`):

```bash
./docs/deploy-android.sh
```

## 3. Build release firmato

Flusso completo pronto in `docs/release-commands.txt`. Punti critici:

1. **Versione**: incrementa `NATIVEPHP_APP_VERSION` nel `.env` (il version_code si auto-incrementa; serve a far girare le migration on-device all'update).
2. **Dati personali fuori dall'APK**: sposta via il seed prima del build, poi ripristinalo:
   ```bash
   mv database/seed.sqlite /tmp/seed-personale.sqlite
   php artisan native:package android --build-type=release
   mv /tmp/seed-personale.sqlite database/seed.sqlite
   ```
   Il guard `guard-release-personal-data.sh` blocca il build se il seed e' ancora presente.
3. APK firmato in `nativephp/android/app/build/outputs/apk/release/app-release.apk`. Usa SEMPRE lo stesso keystore (`credentials/app-release-key.jks`).

## Note

- Mai `migrate:fresh`/`refresh`/`db:wipe`: il DB on-device ha dati reali, migration append-only (hook `block-destructive-db.sh` lo blocca).
- `native:run` NON rigenera il CSS: il build asset e' gia' dentro `deploy-android.sh`.
