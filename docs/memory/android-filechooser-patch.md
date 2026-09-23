---
name: android-filechooser-patch
description: "Su Android l'<input type=file> non apre nulla; patch onShowFileChooser nel runtime NativePHP, da riapplicare dopo native:install"
metadata: 
  node_type: memory
  type: project
  originSessionId: 1991b9e9-15cc-4742-9496-519416d29eb7
---

Nel runtime Android di NativePHP Mobile (3.3.6) il `WebChromeClient` in
`nativephp/android/.../network/WebViewManager.kt` NON implementa `onShowFileChooser`,
quindi dentro l'app ogni `<input type="file">` (i 3 campi di import in
`settings/import`, componente `x-dropzone`) non apre nulla al tap. NativePHP Mobile
non ha nemmeno un document picker nativo per file generici (solo `Camera::pickImages`).

**Fix:** override `onShowFileChooser` in `WebViewManager.kt` che delega a
`MainActivity.showFileChooser(...)` (launcher `StartActivityForResult` +
`FileChooserParams.parseResult`). `params.createIntent()` rispetta `accept=".zip"/".json"`.

**Why:** `nativephp/` e' gitignorata (`*`) e viene rigenerata da `php artisan native:install`,
che cancella la patch. Per questo la modifica e' salvata come `docs/android-filechooser.patch`
+ script idempotente `docs/patch-android-filechooser.sh`. NB: anche `/docs/*` e' gitignorato
(file locali, non in git), coerente con gli altri script di build/deploy in `docs/`.

Verificata sull'emulatore Pixel_7 (2026-07-14): tap sul campo import apre il document
picker di sistema, filtro `accept` rispettato, e l'import backup `.json` completa col
toast "Dati importati".

**How to apply:** dopo ogni `native:install` lanciare `docs/patch-android-filechooser.sh`,
poi ricompilare l'APK (vedi [[native-run-tty-workaround]]). Lo script e' no-op se gia' applicata.
