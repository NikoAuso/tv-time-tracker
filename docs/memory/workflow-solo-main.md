---
name: workflow-solo-main
description: "Per tv-time-tracker si lavora solo su main, niente branch/worktree dedicati"
metadata: 
  node_type: memory
  type: feedback
  originSessionId: 42e11ef8-cde6-4315-a75f-a2a523bf7d58
---

Per il progetto tv-time-tracker l'utente lavora **solo sul branch `main`**: niente branch dedicati né worktree per le feature.

**Why:** richiesto esplicitamente il 2026-07-06 dopo il merge della feature "stato Concluse serie"; i worktree con vendor/symlink creavano attrito nel far girare i test.

**How to apply:** fare le modifiche e i commit direttamente su `main` in `~/Scrivania/PROGETTI/tv-time-tracker`. Questo sovrascrive la convenzione branch+worktree del CLAUDE.md globale, limitatamente a questo progetto. Restano validi: commit separati per piano logico, messaggi in inglese, mai push autonomo, `vendor/bin/pint --dirty` + PHPStan prima di chiudere.
