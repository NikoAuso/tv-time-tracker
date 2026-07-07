<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('sends a brand-new install (empty DB) to the token screen', function () {
    // Nessun utente: primo avvio di un build pulito (senza seed).
    $this->get(route('dashboard'))->assertRedirect(route('token.edit'));
    $this->get(route('token.edit'))->assertOk();
});
