<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('sends a brand-new install (empty DB) to the import screen to set a token', function () {
    // Nessun utente: primo avvio di un build pulito (senza seed).
    $this->get(route('dashboard'))->assertRedirect(route('import.edit'));
    $this->get(route('import.edit'))->assertOk();
});
