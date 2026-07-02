<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');

    Route::livewire('library', 'pages::library')->name('library');
    Route::livewire('shows/{show}', 'pages::show')->name('shows.show');
    Route::livewire('episodes/{episode}', 'pages::episode')->name('episodes.show');
    Route::livewire('stats', 'pages::stats')->name('stats');
});

require __DIR__.'/settings.php';
