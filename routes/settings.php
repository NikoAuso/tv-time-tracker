<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['pin'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    // Sempre raggiungibile: è dove si inserisce il token TMDB richiesto dal gate.
    Route::livewire('settings/import', 'pages::settings.import')->name('import.edit');

    Route::middleware(['tmdb'])->group(function () {
        Route::livewire('settings/profile', 'pages::settings.profile')->name('profile.edit');
        Route::livewire('settings/appearance', 'pages::settings.appearance')->name('appearance.edit');
        Route::livewire('settings/pin', 'pages::settings.pin')->name('pin.edit');
    });
});
