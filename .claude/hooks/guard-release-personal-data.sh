#!/usr/bin/env bash
# PreToolUse(Bash): impedisce di imbarcare dati personali in un build pubblico.
# Un build release/bundle embedda database/seed.sqlite nell'APK; il flusso
# documentato (docs/release-commands.txt) sposta via il seed prima del build.
# Se il seed e' ancora presente durante un release/bundle, blocca e ricorda.
set -euo pipefail

cmd=$(jq -r '.tool_input.command // empty')
seed="${CLAUDE_PROJECT_DIR:-.}/database/seed.sqlite"

if printf '%s' "$cmd" | grep -Eq 'artisan[[:space:]]+native:(package|build).*(release|bundle)' && [[ -f "$seed" ]]; then
  echo "BLOCCATO: build release/bundle con database/seed.sqlite ancora presente." >&2
  echo "Verrebbe pubblicata la libreria personale dentro l'APK." >&2
  echo "Sposta via il seed prima del build, poi ripristinalo:" >&2
  echo "  mv database/seed.sqlite /tmp/seed-personale.sqlite" >&2
  echo "  <build release>" >&2
  echo "  mv /tmp/seed-personale.sqlite database/seed.sqlite" >&2
  exit 2
fi

exit 0
