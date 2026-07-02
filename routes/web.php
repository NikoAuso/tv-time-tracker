<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::livewire('library', 'pages::library')->name('library');
    Route::livewire('shows/{show}', 'pages::show')->name('shows.show');
});

require __DIR__.'/settings.php';
