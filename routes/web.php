<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

// Sblocco PIN: raggiungibile senza il gate del PIN, ma con utente autenticato.
Route::livewire('unlock', 'pages::unlock')->name('unlock');
Route::post('lock', function () {
    session()->forget('pin_unlocked');

    return redirect()->route('unlock');
})->name('lock');

Route::middleware(['pin', 'tmdb'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');

    Route::livewire('library', 'pages::library')->name('library');
    Route::livewire('search', 'pages::search')->name('search');
    Route::livewire('shows/{show}', 'pages::show')->name('shows.show');
    Route::livewire('movies/{movie}', 'pages::movie')->name('movies.show');
    Route::livewire('episodes/{episode}', 'pages::episode')->name('episodes.show');
    Route::livewire('lists', 'pages::lists')->name('lists');
    Route::livewire('lists/{userList}', 'pages::list')->name('lists.show');
    Route::livewire('stats', 'pages::stats')->name('stats');
});

require __DIR__.'/settings.php';
