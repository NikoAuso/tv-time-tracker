# Memoria progetto — tv-time-tracker

- [Workflow solo main](workflow-solo-main.md) — niente branch/worktree, si committa direttamente su main
- [Seed sqlite](seed-sqlite-workflow.md) — l'app si semina da database/seed.sqlite, rigenerarlo dopo modifiche ai dati
- [native:run TTY](native-run-tty-workaround.md) — lanciare native:run con `script -qfec` per lo pseudo-TTY, altrimenti fallisce da Bash
- [Dati personali fuori da push/release](strip-personal-data-before-push-release.md) — prima di ogni push o release togliere/oscurare i dati personali, poi ripristinarli in locale
- [Patch Android file chooser](android-filechooser-patch.md) — <input type=file> non apre nulla su Android; patch onShowFileChooser da riapplicare dopo native:install
- [Build Android richiede JDK 17](android-build-needs-jdk17.md) — il java di sistema è 25, incompatibile col Kotlin di NativePHP; deploy-android.sh auto-seleziona un JDK 17/21
- [Pint: niente static_lambda](pint-no-static-lambda-pest.md) — rompe i test Pest; pint.json resta solo preset laravel
- [Migration on-update](on-device-migrations-on-update.md) — su update le migration girano solo se bumpi la versione nel .env; il DB persiste, dati salvi
- [TvTimeTracker](tvtimetracker-project.md) — app personale tipo TV Time, target mobile Android via NativePHP, stack/decisioni e stato avanzamento
