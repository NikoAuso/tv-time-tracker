---
name: pint-no-static-lambda-pest
description: Non abilitare static_lambda in pint.json — rompe i test Pest (closure it/test non possono essere static)
metadata: 
  node_type: memory
  type: project
  originSessionId: 1991b9e9-15cc-4742-9496-519416d29eb7
---

Il progetto usa **Pest**. La regola Pint `static_lambda` (marca `static` le closure senza
`$this`) rende static anche le closure `it(...)`/`test(...)`, che Pest rifiuta a runtime:
`Test closure must not be static`. Quindi **non abilitare `static_lambda`** in `pint.json`
(deve restare `{"preset": "laravel"}`). Vale anche per l'inspection PhpStorm "closure can be
static": su codice applicativo è corretta ma non auto-correggibile con Pint qui, quindi
ignorabile.

`@throws`: non è una convenzione del progetto (nessun `@throws` in `app/`), quindi le
segnalazioni IDE sui metodi che usano `DB::transaction` sono ignorabili.
