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

it('filters the library by type', function () {
    $user = User::factory()->create();
    $show = Show::factory()->create(['name' => 'House']);
    UserShow::factory()->create(['user_id' => $user->id, 'show_id' => $show->id, 'status' => 'following']);
    $episode = Episode::factory()->create(['show_id' => $show->id, 'season_number' => 1, 'episode_number' => 1, 'air_date' => now()->subDay()]);
    WatchedEpisode::factory()->create(['user_id' => $user->id, 'episode_id' => $episode->id]);
    $movie = Movie::factory()->create(['title' => 'Fury']);
    UserMovie::factory()->create(['user_id' => $user->id, 'movie_id' => $movie->id, 'status' => 'watched']);

    // Default: Serie, stato "Concluse".
    Livewire::actingAs($user)->test('pages::library')
        ->set('status', 'done')
        ->assertSee('House')
        ->assertDontSee('Fury')
        ->set('type', 'movies')
        ->assertSee('Fury')
        ->assertDontSee('House');
});

it('separates watched movies from the watchlist', function () {
    $user = User::factory()->create();
    $watched = Movie::factory()->create(['title' => 'Fury']);
    $planned = Movie::factory()->create(['title' => 'Dune']);
    UserMovie::factory()->create(['user_id' => $user->id, 'movie_id' => $watched->id, 'status' => 'watched']);
    UserMovie::factory()->create(['user_id' => $user->id, 'movie_id' => $planned->id, 'status' => 'watchlist']);

    Livewire::actingAs($user)->test('pages::library')
        ->set('type', 'movies')
        ->set('status', 'done')
        ->assertSee('Fury')
        ->assertDontSee('Dune')
        ->set('status', 'watchlist')
        ->assertSee('Dune')
        ->assertDontSee('Fury');
});

it('classifies series into da iniziare, in corso and concluse', function () {
    $user = User::factory()->create();

    $toStart = Show::factory()->create(['name' => 'ToStartShow']);
    UserShow::factory()->create(['user_id' => $user->id, 'show_id' => $toStart->id, 'status' => 'watchlist']);

    $inProgress = Show::factory()->create(['name' => 'InProgressShow']);
    UserShow::factory()->create(['user_id' => $user->id, 'show_id' => $inProgress->id, 'status' => 'following']);
    Episode::factory()->create(['show_id' => $inProgress->id, 'season_number' => 1, 'episode_number' => 1, 'air_date' => now()->subDay()]);

    $done = Show::factory()->create(['name' => 'DoneShow']);
    UserShow::factory()->create(['user_id' => $user->id, 'show_id' => $done->id, 'status' => 'following']);
    $doneEp = Episode::factory()->create(['show_id' => $done->id, 'season_number' => 1, 'episode_number' => 1, 'air_date' => now()->subDay()]);
    WatchedEpisode::factory()->create(['user_id' => $user->id, 'episode_id' => $doneEp->id]);

    Livewire::actingAs($user)->test('pages::library')
        ->set('type', 'series')
        ->set('status', 'watchlist')
        ->assertSee('ToStartShow')->assertDontSee('InProgressShow')->assertDontSee('DoneShow')
        ->set('status', 'in_progress')
        ->assertSee('InProgressShow')->assertDontSee('ToStartShow')->assertDontSee('DoneShow')
        ->set('status', 'done')
        ->assertSee('DoneShow')->assertDontSee('InProgressShow')->assertDontSee('ToStartShow');
});

it('re-opens a concluse series when a newly aired episode is unwatched', function () {
    $user = User::factory()->create();
    $show = Show::factory()->create(['name' => 'ReturningShow']);
    UserShow::factory()->create(['user_id' => $user->id, 'show_id' => $show->id, 'status' => 'following']);
    $seen = Episode::factory()->create(['show_id' => $show->id, 'season_number' => 1, 'episode_number' => 1, 'air_date' => now()->subMonth()]);
    WatchedEpisode::factory()->create(['user_id' => $user->id, 'episode_id' => $seen->id]);
    // Nuovo episodio già uscito ma non visto: la serie torna "In corso".
    Episode::factory()->create(['show_id' => $show->id, 'season_number' => 1, 'episode_number' => 2, 'air_date' => now()->subDay()]);

    Livewire::actingAs($user)->test('pages::library')
        ->set('type', 'series')
        ->set('status', 'in_progress')
        ->assertSee('ReturningShow')
        ->set('status', 'done')
        ->assertDontSee('ReturningShow');
});

it('folds archived shows into the completion states by watched episodes', function () {
    $user = User::factory()->create();
    $show = Show::factory()->create(['name' => 'ArchivedDone']);
    UserShow::factory()->create(['user_id' => $user->id, 'show_id' => $show->id, 'status' => 'archived']);
    $ep = Episode::factory()->create(['show_id' => $show->id, 'season_number' => 1, 'episode_number' => 1, 'air_date' => now()->subDay()]);
    WatchedEpisode::factory()->create(['user_id' => $user->id, 'episode_id' => $ep->id]);

    Livewire::actingAs($user)->test('pages::library')
        ->set('type', 'series')
        ->set('status', 'done')
        ->assertSee('ArchivedDone');
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

it('marks a series as "da vedere" from its detail page', function () {
    $user = User::factory()->create();
    $show = Show::factory()->create();

    Livewire::actingAs($user)->test('pages::show', ['show' => $show])
        ->call('addWatchlist')
        ->assertOk();

    expect(UserShow::where('user_id', $user->id)->where('show_id', $show->id)->value('status'))
        ->toBe('watchlist');
});

it('promotes a "da vedere" series to following when an episode is watched', function () {
    $user = User::factory()->create();
    $show = Show::factory()->create();
    UserShow::factory()->create(['user_id' => $user->id, 'show_id' => $show->id, 'status' => 'watchlist']);
    $episode = Episode::factory()->create(['show_id' => $show->id, 'season_number' => 1, 'episode_number' => 1]);

    Livewire::actingAs($user)->test('pages::show', ['show' => $show])
        ->call('toggle', $episode->id);

    expect(UserShow::where('user_id', $user->id)->where('show_id', $show->id)->value('status'))
        ->toBe('following');
});

it('removes a series from the library', function () {
    $user = User::factory()->create();
    $show = Show::factory()->create();
    UserShow::factory()->create(['user_id' => $user->id, 'show_id' => $show->id, 'status' => 'watchlist']);

    Livewire::actingAs($user)->test('pages::show', ['show' => $show])
        ->call('remove')
        ->assertOk();

    expect(UserShow::where('user_id', $user->id)->where('show_id', $show->id)->exists())->toBeFalse();
});
