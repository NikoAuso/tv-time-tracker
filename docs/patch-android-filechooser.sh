#!/usr/bin/env bash
# Ri-applica l'override onShowFileChooser al runtime Android di NativePHP.
#
# Perche' serve: nativephp/ e' gitignorata e viene rigenerata da
# 'php artisan native:install'. Il WebChromeClient generato NON implementa
# onShowFileChooser, quindi dentro l'app un <input type="file"> (i campi di
# import in settings/import) non apre nulla al tap. Questa patch lo collega al
# document picker di sistema (gestisce .zip e .json nativamente).
#
# Idempotente: rilanciare dopo ogni 'native:install', poi ricompilare l'APK.
set -euo pipefail
cd "$(dirname "$0")/.."

target="nativephp/android/app/src/main/java/com/nativephp/mobile/network/WebViewManager.kt"

if [[ ! -f "$target" ]]; then
    echo "Runtime Android assente. Esegui prima: php artisan native:install" >&2
    exit 1
fi

if grep -q "onShowFileChooser" "$target"; then
    echo "Patch file chooser gia' applicata."
    exit 0
fi

patch -p1 < docs/android-filechooser.patch
echo "Patch applicata. Ora ricompila: php artisan native:run android"
