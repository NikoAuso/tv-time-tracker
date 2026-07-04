<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['pin'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::livewire('settings/profile', 'pages::settings.profile')->name('profile.edit');
    Route::livewire('settings/appearance', 'pages::settings.appearance')->name('appearance.edit');
    Route::livewire('settings/pin', 'pages::settings.pin')->name('pin.edit');
    Route::livewire('settings/import', 'pages::settings.import')->name('import.edit');
});
