---
name: native-run-tty-workaround
description: "native:run android va lanciato con uno pseudo-TTY dal tool Bash, altrimenti fallisce"
metadata: 
  node_type: memory
  type: project
  originSessionId: 3058a70b-1b85-4d9d-aeaa-56f870269519
---

`php artisan native:run android` (NativePHP) fallisce se lanciato dal tool Bash senza terminale interattivo, con `TTY mode requires /dev/tty to be read/writable`. Il bundle viene creato (~40 MB) ma l'install/run no.

**How to apply:** lanciarlo allocando uno pseudo-TTY: `script -qfec "php artisan native:run android" /dev/null`, in background con timeout ampio (il build Gradle richiede alcuni minuti la prima volta, poi il daemon lo velocizza). Prima del deploy: `npm run build` (native:run NON rigenera il CSS) + `php artisan optimize:clear` + reinstallazione pulita `adb uninstall com.nikoauso.tvtimetracker`. Vedi [[seed-sqlite-workflow]]. Package id: `com.nikoauso.tvtimetracker`.
