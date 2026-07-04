<?php

use App\Models\Movie;
use App\Models\User;
use App\Models\UserMovie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

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
        ->assertSee(route('movies.show', $movie), false);
});
