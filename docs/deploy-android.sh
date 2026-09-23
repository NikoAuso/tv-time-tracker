#!/usr/bin/env bash
#
# Ricompila gli asset e reinstalla l'app sull'emulatore/device Android da zero.
# L'emulatore dev'essere gia' avviato (./docs/run-emulator.sh).
#
# Perche' `script -qfec`: `native:run` richiede un TTY e senza terminale
# interattivo fallisce con "TTY mode requires /dev/tty to be read/writable".
# Avvolgendolo in `script` gli si alloca uno pseudo-TTY, cosi' funziona anche
# quando lanciato da tool/CI. Il primo build Gradle richiede alcuni minuti.
#
# Perche' reinstallazione pulita: il solo `pm clear` non basta a rileggere
# schema/dati freschi; serve disinstallare cosi' l'app riparte dal seed.sqlite.
#
# Uso:
#   ./docs/deploy-android.sh
#
set -euo pipefail

cd "$(dirname "$0")/.."

APP_ID="com.nikoauso.tvtimetracker"

# NativePHP builda con Gradle 8.13 + Kotlin, il cui compilatore non riconosce
# Java > 21 (fallisce con "IllegalArgumentException: <versione>"). Se il java di
# default e' troppo nuovo, seleziona un JDK 17/21 completo (con javac) dal sistema.
select_build_jdk() {
    local d m
    if [[ -n "${JAVA_HOME:-}" && -x "$JAVA_HOME/bin/javac" ]]; then
        m=$("$JAVA_HOME/bin/javac" -version 2>&1 | grep -oE '[0-9]+' | head -1)
        [[ "$m" == "17" || "$m" == "21" ]] && { echo "==> JDK build: $JAVA_HOME (javac $m)"; return; }
    fi
    for d in /usr/lib/jvm/*/; do
        [[ -x "${d}bin/javac" ]] || continue
        m=$("${d}bin/javac" -version 2>&1 | grep -oE '[0-9]+' | head -1)
        if [[ "$m" == "17" || "$m" == "21" ]]; then
            export JAVA_HOME="${d%/}"
            echo "==> JDK build: $JAVA_HOME (javac $m)"
            return
        fi
    done
    echo "ERRORE: serve un JDK 17 o 21 completo (con javac); trovato solo Java $(javac -version 2>&1 | grep -oE '[0-9]+' | head -1 || echo '?')." >&2
    echo "  Ubuntu: sudo apt install -y openjdk-17-jdk" >&2
    echo "  Fedora: sudo dnf install -y java-17-openjdk-devel" >&2
    exit 1
}
select_build_jdk

echo "==> Build asset (native:run NON rigenera il CSS)"
npm run build

echo "==> Pulizia cache (route/config/schema)"
php artisan optimize:clear

echo "==> Reinstallazione pulita: disinstallo ${APP_ID}"
adb uninstall "${APP_ID}" || true

echo "==> Build + install on-device (pseudo-TTY per native:run)"
script -qfec "php artisan native:run android" /dev/null
