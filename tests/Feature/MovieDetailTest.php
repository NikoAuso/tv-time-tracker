<?php

use App\Models\Movie;
use App\Models\User;
use App\Models\UserMovie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('shows genres, trailer and streaming providers on the detail page', function () {
    config(['services.tmdb.token' => 'fake-token']);
    Http::fake([
        '*/watch/providers*' => Http::response(['results' => ['IT' => [
            'link' => 'https://justwatch/it',
            'flatrate' => [['provider_name' => 'Netflix', 'logo_path' => '/n.jpg']],
        ]]]),
        '*/videos*' => Http::response(['results' => [
            ['site' => 'YouTube', 'type' => 'Trailer', 'key' => 'abc123'],
        ]]),
        '*/movie/*' => Http::response(['backdrop_path' => '/bd.jpg']),
    ]);

    $user = User::factory()->create();
    $movie = Movie::factory()->create(['tmdb_id' => 42, 'genres' => ['Azione', 'Dramma']]);

    Livewire::actingAs($user)->test('pages::movie', ['movie' => $movie])
        ->assertSee('Azione')
        ->assertSee('Dramma')
        ->assertSee('Dove guardarlo')
        ->assertSee('/bd.jpg', false)
        ->assertSee('youtube.com/watch?v=abc123', false);
});

it('marks a movie as watched from its detail page', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();

    Livewire::actingAs($user)->test('pages::movie', ['movie' => $movie])
        ->call('markWatched');

    $entry = UserMovie::where('user_id', $user->id)->where('movie_id', $movie->id)->first();
    expect($entry)->not->toBeNull()
        ->and($entry->status)->toBe('watched')
        ->and($entry->watched_at)->not->toBeNull();
});

it('adds a movie to the watchlist', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();

    Livewire::actingAs($user)->test('pages::movie', ['movie' => $movie])
        ->call('addWatchlist');

    expect(UserMovie::where('user_id', $user->id)->where('movie_id', $movie->id)->value('status'))
        ->toBe('watchlist');
});

it('promotes a watchlisted movie to watched', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();
    UserMovie::factory()->create([
        'user_id' => $user->id,
        'movie_id' => $movie->id,
        'status' => 'watchlist',
        'watched_at' => null,
    ]);

    Livewire::actingAs($user)->test('pages::movie', ['movie' => $movie])
        ->call('markWatched');

    expect(UserMovie::where('user_id', $user->id)->where('movie_id', $movie->id)->count())->toBe(1)
        ->and(UserMovie::where('user_id', $user->id)->where('movie_id', $movie->id)->value('status'))->toBe('watched');
});

it('increments the rewatch count from the visto modal', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();
    UserMovie::factory()->create([
        'user_id' => $user->id, 'movie_id' => $movie->id,
        'status' => 'watched', 'rewatch_count' => 0,
    ]);

    Livewire::actingAs($user)->test('pages::movie', ['movie' => $movie])
        ->call('rewatch');

    $entry = UserMovie::where('user_id', $user->id)->where('movie_id', $movie->id)->first();
    expect($entry->status)->toBe('watched')
        ->and($entry->rewatch_count)->toBe(1)
        ->and($entry->watched_at)->not->toBeNull();
});

it('unwatches a movie back to the watchlist from the visto modal', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();
    UserMovie::factory()->create([
        'user_id' => $user->id, 'movie_id' => $movie->id,
        'status' => 'watched', 'watched_at' => now(), 'rewatch_count' => 3,
    ]);

    Livewire::actingAs($user)->test('pages::movie', ['movie' => $movie])
        ->call('unwatch');

    $entry = UserMovie::where('user_id', $user->id)->where('movie_id', $movie->id)->first();
    expect($entry->status)->toBe('watchlist')
        ->and($entry->watched_at)->toBeNull()
        ->and($entry->rewatch_count)->toBe(0);
});

it('removes a movie from the library', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();
    UserMovie::factory()->create(['user_id' => $user->id, 'movie_id' => $movie->id, 'status' => 'watched']);

    Livewire::actingAs($user)->test('pages::movie', ['movie' => $movie])
        ->call('remove');

    expect(UserMovie::where('user_id', $user->id)->where('movie_id', $movie->id)->exists())->toBeFalse();
});

it('links to the movie detail page from the library', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create(['title' => 'Fury']);
    UserMovie::factory()->create(['user_id' => $user->id, 'movie_id' => $movie->id, 'status' => 'watched']);

    Livewire::actingAs($user)->test('pages::library')
        ->set('type', 'movies')
        ->set('status', 'done')
        ->assertSee(route('movies.show', $movie), false);
});
