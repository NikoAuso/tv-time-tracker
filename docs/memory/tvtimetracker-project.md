---
name: tvtimetracker-project
description: "TvTimeTracker — app personale tipo TV Time su Android via NativePHP: decisioni non ovvie, trappole note e backlog"
metadata:
  node_type: memory
  type: project
  originSessionId: ed809246-a8ce-4375-9191-d54c4d01216f
  modified: 2026-10-11T16:30:06.084Z
---

Ricrea TV Time (chiuso il 2026-07-15) per **uso personale**, partendo dall'export GDPR in `docs/Dati TV Time` (CSV, mai committati). App Android on-device via NativePHP Mobile, codice pubblico su GitHub, APK firmati nelle release. Rilasciate v1.0.0 (2026-07-07), v1.1.0 (2026-08-29), v1.2.0 (2026-10-04), v1.2.1 (2026-10-11). Le release fino a v1.2.0 non hanno più l'APK allegato (rimosso il 2026-10-11 perché conteneva dati personali, vedi [[strip-personal-data-before-push-release]]): l'unico APK scaricabile è quello dell'ultima release.

**Decisioni non ovvie:**
- Gli `s_id`/`tv_show_id` dell'export sono **ID TheTVDB**, non TMDB: si risolvono con `/find/{id}?external_source=tvdb_id`.
- Niente login: un solo utente auto-loggato (`AutoLoginSingleUser`) + PIN locale opzionale. Multi-utente e SEO fuori scope.
- **NativePHP resta su 3.x** (deciso 2026-10-11): la v4 punta a riscrivere la UI Livewire/Flux in componenti nativi EDGE e non aggiunge il file chooser della WebView; la linea 3.x riceve ancora patch (3.3.9 del 2026-10-02). Rivalutare solo se la 3.x smette di essere mantenuta.
- **Notifiche locali e job in background** esistono solo nel bundle a pagamento "Background + Local" del marketplace NativePHP ($149, verificato 2026-10-11): fuori portata finché l'utente non decide di comprarlo.

**Trappole note:**
- Se una modifica UI non compare on-device: view Blade compilate stale in `persisted_data` (sopravvivono ai reinstall). Già escluse via `cleanup_exclude_files` in `config/nativephp.php`.
- Emulatore su Wayland+NVIDIA: serve `QT_QPA_PLATFORM=xcb` con `-gpu host`, altrimenti resta offline (`docs/run-emulator.sh`).
- Bottom nav mobile: `<a>` semplici in `grid grid-cols-4`, non `flux:link` (sfasa icona/testo); safe-area richiede `viewport-fit=cover`.

**Backlog:** pulizia dead-code Fortify residuo. La chiave TMDB esposta negli screenshot della guida (luglio) è stata rigenerata dall'utente il 2026-10-11: la questione è chiusa. `Http::pool` (stagioni e provider) funziona on-device: verificato dall'utente con un resync reale sulla v1.2.1 (2026-10-11).

Correlati: [[seed-sqlite-workflow]], [[on-device-migrations-on-update]], [[android-filechooser-patch]], [[strip-personal-data-before-push-release]].
