#!/usr/bin/env bash
#
# Avvia l'emulatore Android con accelerazione GPU hardware (NVIDIA) su Wayland.
#
# Perche' QT_QPA_PLATFORM=xcb: in una sessione Wayland l'emulatore con "-gpu host"
# non completa il boot (il device resta "offline"). Forzandolo su XWayland (xcb)
# l'accelerazione hardware funziona e la UI resta fluida (niente "app non risponde").
#
# Uso:
#   ./docs/run-emulator.sh                 # avvia l'AVD Pixel_7 in foreground
#   ./docs/run-emulator.sh Nome_AVD        # avvia un altro AVD
#   ./docs/run-emulator.sh --deploy        # avvia (se serve), attende il boot e deploya
#   ./docs/run-emulator.sh Nome_AVD --deploy
#
set -euo pipefail

cd "$(dirname "$0")/.."

DEPLOY=false
AVD="Pixel_7"
for arg in "$@"; do
    case "$arg" in
        --deploy) DEPLOY=true ;;
        -*) echo "Opzione sconosciuta: $arg" >&2; exit 1 ;;
        *) AVD="$arg" ;;
    esac
done

export ANDROID_HOME="${ANDROID_HOME:-$HOME/Android/Sdk}"
EMULATOR="$ANDROID_HOME/emulator/emulator"
EMU_OPTS=(-avd "$AVD" -gpu host -memory 4096 -no-boot-anim)

if ! "$EMULATOR" -list-avds | grep -qx "$AVD"; then
    echo "AVD '$AVD' non trovato. Disponibili:" >&2
    "$EMULATOR" -list-avds >&2
    exit 1
fi

# Modalita' classica: emulatore in foreground (nessun deploy).
if [ "$DEPLOY" = false ]; then
    exec env QT_QPA_PLATFORM=xcb "$EMULATOR" "${EMU_OPTS[@]}"
fi

# Modalita' --deploy: avvia in background solo se non c'e' gia' un emulatore,
# attende il boot completo, poi ricompila e reinstalla l'app.
if adb devices | grep -q "emulator-"; then
    echo "==> Emulatore gia' in esecuzione"
else
    echo "==> Avvio emulatore $AVD in background"
    env QT_QPA_PLATFORM=xcb "$EMULATOR" "${EMU_OPTS[@]}" >/dev/null 2>&1 &
fi

echo "==> Attendo il boot completo del device..."
adb wait-for-device
until [ "$(adb shell getprop sys.boot_completed 2>/dev/null | tr -d '\r')" = "1" ]; do
    sleep 2
done

echo "==> Boot completato, avvio deploy"
exec "$(dirname "$0")/deploy-android.sh"
