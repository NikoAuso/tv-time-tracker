#!/usr/bin/env bash
# PreToolUse(Bash): blocca i comandi che distruggono il DB.
# L'app gira on-device con dati reali (libreria TV Time) e le migration sono
# append-only: migrate:fresh/refresh e db:wipe cancellerebbero tutto. Vedi CLAUDE.md.
set -euo pipefail

cmd=$(jq -r '.tool_input.command // empty')

if printf '%s' "$cmd" | grep -Eq 'artisan[[:space:]]+(migrate:(fresh|refresh)|db:wipe)'; then
  echo "BLOCCATO: comando distruttivo sul DB ($cmd)." >&2
  echo "L'app e' installata su device con dati reali e usa migration append-only." >&2
  echo "Per uno schema nuovo: scrivi una nuova migration, non resettare il DB." >&2
  exit 2
fi

exit 0
