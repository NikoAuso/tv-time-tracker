---
name: android-build-needs-jdk17
description: Il build Android NativePHP richiede JDK 17/21; il java di sistema è 25 (troppo nuovo per Kotlin). deploy-android.sh auto-seleziona
metadata: 
  node_type: memory
  type: project
  originSessionId: 1991b9e9-15cc-4742-9496-519416d29eb7
---

Il `java` di default del sistema è OpenJDK **25**, ma il compilatore Kotlin di NativePHP
(Gradle 8.13) non lo riconosce: il build fallisce presto con
`java.lang.IllegalArgumentException: 25.0.x` (in `JavaVersion.parse`). Serve un JDK
**17 o 21 completo** (con `javac`).

**Why:** sul sistema le versioni 17/21 erano presenti solo come JRE (senza `javac`);
il JDK 17 completo va installato con `sudo apt install openjdk-17-jdk` (Fedora:
`sudo dnf install java-17-openjdk-devel`).

**How to apply:** `docs/deploy-android.sh` ora auto-seleziona un JDK 17/21 da
`/usr/lib/jvm/*` (funzione `select_build_jdk`); se manca stampa il comando di install.
Correlato: [[native-run-tty-workaround]], [[android-filechooser-patch]].

**APK release firmato:** `php artisan native:package android --no-interaction`
(default `--build-type=release` → `assembleRelease` → APK firmato in
`nativephp/android/app/build/outputs/apk/release/app-release.apk`; firma dalle
properties `MYAPP_UPLOAD_*` in `nativephp/android/gradle.properties`, keystore
`credentials/app-release-key.jks`). NB: `native:build` in questa versione e' **solo iOS**;
`--build-type=bundle` per l'AAB del Play Store. Ricordarsi `JAVA_HOME` sul JDK 17.
NativePHP stampa un warning fuorviante "unsigned"; l'APK e' firmato v2 (verifica:
`apksigner verify --print-certs <apk>`).

**TTY nel build (confermato v1.1.0):** lo step Gradle `assembleRelease` fallisce con
`Build failed: TTY mode requires /dev/tty to be read/writable` se `native:package` gira
da Bash senza terminale. Wrappare come per `native:run` (vedi [[native-run-tty-workaround]]):
`script -qfec "JAVA_HOME=... PATH=... php artisan native:package android --no-interaction" /dev/null`.
NB: NativePHP **auto-incrementa il versionCode Android dell'APK** ignorando
`NATIVEPHP_APP_VERSION_CODE` di `.env` (in v1.1.0: env=14 → APK=15); il valore `.env` conta
invece per l'identita' che innesca l'estrazione/migrate on-device (vedi [[on-device-migrations-on-update]]).
