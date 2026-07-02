<?php

use App\Models\Episode;
use App\Models\Movie;
use App\Models\Show;
use App\Models\User;
use App\Models\UserMovie;
use App\Models\UserShow;
use App\Models\WatchedEpisode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('shows the followed shows in the library', function () {
    $user = User::factory()->create();
    $show = Show::factory()->create(['name' => 'House']);
    UserShow::factory()->create(['user_id' => $user->id, 'show_id' => $show->id, 'status' => 'following']);

    $this->actingAs($user)->get(route('library'))
        ->assertOk()
        ->assertSee('House');
});

it('shows series and movies together, filterable by type', function () {
    $user = User::factory()->create();
    $show = Show::factory()->create(['name' => 'House']);
    UserShow::factory()->create(['user_id' => $user->id, 'show_id' => $show->id, 'status' => 'following']);
    $movie = Movie::factory()->create(['title' => 'Fury']);
    UserMovie::factory()->create(['user_id' => $user->id, 'movie_id' => $movie->id, 'status' => 'watched']);

    Livewire::actingAs($user)->test('pages::library')
        ->assertSee('House')
        ->assertSee('Fury')
        ->set('type', 'movies')
        ->assertSee('Fury')
        ->assertDontSee('House')
        ->set('type', 'series')
        ->assertSee('House')
        ->assertDontSee('Fury');
});

it('separates watched movies from the watchlist', function () {
    $user = User::factory()->create();
    $watched = Movie::factory()->create(['title' => 'Fury']);
    $planned = Movie::factory()->create(['title' => 'Dune']);
    UserMovie::factory()->create(['user_id' => $user->id, 'movie_id' => $watched->id, 'status' => 'watched']);
    UserMovie::factory()->create(['user_id' => $user->id, 'movie_id' => $planned->id, 'status' => 'watchlist']);

    Livewire::actingAs($user)->test('pages::library')
        ->set('type', 'movies')
        ->set('status', 'library')
        ->assertSee('Fury')
        ->assertDontSee('Dune')
        ->set('status', 'watchlist')
        ->assertSee('Dune')
        ->assertDontSee('Fury');
});

it('toggles an episode watched state', function () {
    $user = User::factory()->create();
    $show = Show::factory()->create();
    $episode = Episode::factory()->create(['show_id' => $show->id, 'season_number' => 1, 'episode_number' => 1]);
    WatchedEpisode::factory()->create(['user_id' => $user->id, 'episode_id' => $episode->id]);

    Livewire::actingAs($user)->test('pages::show', ['show' => $show])
        ->call('toggle', $episode->id)
        ->assertOk();
    expect(WatchedEpisode::count())->toBe(0);

    Livewire::actingAs($user)->test('pages::show', ['show' => $show])
        ->call('toggle', $episode->id);
    expect(WatchedEpisode::where('user_id', $user->id)->where('episode_id', $episode->id)->exists())->toBeTrue();
});

it('adds a watched episode manually', function () {
    $user = User::factory()->create();
    $show = Show::factory()->create();

    Livewire::actingAs($user)->test('pages::show', ['show' => $show])
        ->set('newSeason', 2)
        ->set('newEpisode', 5)
        ->call('addEpisode')
        ->assertHasNoErrors();

    $episode = Episode::where('show_id', $show->id)->where('season_number', 2)->where('episode_number', 5)->first();
    expect($episode)->not->toBeNull();
    expect(WatchedEpisode::where('user_id', $user->id)->where('episode_id', $episode->id)->exists())->toBeTrue();
});
