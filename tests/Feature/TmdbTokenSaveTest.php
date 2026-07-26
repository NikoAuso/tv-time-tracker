<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('saves the token after TMDB confirms it is valid', function () {
    Http::fake(['*/authentication*' => Http::response(['success' => true], 200)]);
    $user = User::factory()->withoutTmdbToken()->create();

    Livewire::actingAs($user)->test('pages::settings.token')
        ->set('tmdbToken', str_repeat('a', 40))
        ->call('saveToken')
        ->assertHasNoErrors();

    expect($user->fresh()->tmdb_token)->toBe(str_repeat('a', 40));
});

it('rejects an invalid token (TMDB 401) and does not save it', function () {
    Http::fake(['*/authentication*' => Http::response(['success' => false], 401)]);
    $user = User::factory()->withoutTmdbToken()->create();

    Livewire::actingAs($user)->test('pages::settings.token')
        ->set('tmdbToken', str_repeat('a', 40))
        ->call('saveToken')
        ->assertHasErrors('tmdbToken');

    expect($user->fresh()->tmdb_token)->toBeNull();
});
