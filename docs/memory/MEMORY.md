# Memoria progetto — tv-time-tracker

- [Workflow solo main](workflow-solo-main.md) — niente branch/worktree, si committa direttamente su main
- [Seed sqlite](seed-sqlite-workflow.md) — l'app si semina da database/seed.sqlite, rigenerarlo dopo modifiche ai dati
- [native:run TTY](native-run-tty-workaround.md) — lanciare native:run con `script -qfec` per lo pseudo-TTY, altrimenti fallisce da Bash
- [Dati personali fuori da push/release](strip-personal-data-before-push-release.md) — togliere i dati personali prima di push/release; il bundle APK ignora .gitignore, verificarne il contenuto
- [Patch Android file chooser](android-filechooser-patch.md) — <input type=file> non apre nulla su Android; patch onShowFileChooser da riapplicare dopo native:install
- [Build Android richiede JDK 17](android-build-needs-jdk17.md) — il java di sistema è 25, incompatibile col Kotlin di NativePHP; deploy-android.sh auto-seleziona un JDK 17/21
- [Pint: niente static_lambda](pint-no-static-lambda-pest.md) — rompe i test Pest; pint.json resta solo preset laravel
- [Migration on-update](on-device-migrations-on-update.md) — su update le migration girano solo se bumpi la versione nel .env; il DB persiste, dati salvi
- [TvTimeTracker](tvtimetracker-project.md) — decisioni non ovvie (NativePHP resta 3.x, notifiche locali a pagamento), trappole on-device, backlog
- [.env bloccato](env-access-blocked.md) — Claude non legge/modifica .env: il bump versione per le release lo fa l'utente
