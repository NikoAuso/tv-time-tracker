<?php

declare(strict_types=1);

use App\Models\Episode;
use App\Models\Show;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('enriches a show and imports its full episode list from TMDB', function () {
    config(['services.tmdb.token' => 'test-token']);

    Http::fake([
        'https://api.themoviedb.org/3/find/*' => Http::response(['tv_results' => [['id' => 1408]]]),
        'https://api.themoviedb.org/3/tv/1408/season/*' => Http::response(['episodes' => [
            ['id' => 501, 'episode_number' => 1, 'name' => 'Pilot', 'air_date' => '2004-11-16', 'runtime' => 44],
            ['id' => 502, 'episode_number' => 2, 'name' => 'Paternity', 'air_date' => '2004-11-23', 'runtime' => 43],
        ]]),
        'https://api.themoviedb.org/3/tv/1408*' => Http::response([
            'id' => 1408,
            'name' => 'House',
            'poster_path' => '/house.jpg',
            'overview' => 'A doctor.',
            'first_air_date' => '2004-11-16',
            'number_of_episodes' => 176,
            'status' => 'Ended',
            'genres' => [['id' => 18, 'name' => 'Dramma']],
            'seasons' => [['season_number' => 1]],
        ]),
    ]);

    $show = Show::factory()->create(['tvdb_id' => 73255, 'tmdb_id' => null, 'name' => 'House (TVDB)']);

    $this->artisan('shows:sync')->assertSuccessful();

    $show->refresh();
    expect($show->tmdb_id)->toBe(1408)
        ->and($show->poster_path)->toBe('/house.jpg')
        ->and($show->total_episodes)->toBe(176)
        ->and($show->status)->toBe('Ended')
        ->and($show->name)->toBe('House')
        ->and($show->genres)->toBe(['Dramma']);

    expect(Episode::where('show_id', $show->id)->count())->toBe(2);
    $pilot = Episode::where('show_id', $show->id)->where('episode_number', 1)->first();
    expect($pilot->name)->toBe('Pilot')->and($pilot->runtime)->toBe(44);
});

it('marks a show unresolved when TMDB has no match', function () {
    config(['services.tmdb.token' => 'test-token']);
    Http::fake(['https://api.themoviedb.org/3/find/*' => Http::response(['tv_results' => []])]);

    $show = Show::factory()->create(['tvdb_id' => 999999, 'tmdb_id' => null]);

    $this->artisan('shows:sync')->assertSuccessful();

    expect($show->fresh()->tmdb_id)->toBeNull()
        ->and(Episode::count())->toBe(0);
});

it('fails when the TMDB token is missing', function () {
    config(['services.tmdb.token' => null]);
    Show::factory()->create(['tvdb_id' => 73255, 'tmdb_id' => null]);

    $this->artisan('shows:sync')->assertFailed();
});

it('syncs a show already carrying a tmdb_id (e.g. from a JSON backup)', function () {
    config(['services.tmdb.token' => 'test-token']);

    Http::fake([
        'https://api.themoviedb.org/3/tv/1408/season/*' => Http::response(['episodes' => [
            ['id' => 1, 'episode_number' => 1, 'name' => 'Pilot'],
        ]]),
        'https://api.themoviedb.org/3/tv/1408*' => Http::response([
            'id' => 1408, 'name' => 'House', 'overview' => 'A doctor.', 'seasons' => [['season_number' => 1]],
        ]),
    ]);

    // Nessun tvdb_id: risolvibile solo tramite il tmdb_id già presente.
    $show = Show::factory()->create(['tvdb_id' => null, 'tmdb_id' => 1408, 'overview' => null]);

    $this->artisan('shows:sync')->assertSuccessful();

    expect($show->fresh()->overview)->toBe('A doctor.')
        ->and(Episode::where('show_id', $show->id)->count())->toBe(1);
});

it('stores an empty overview when TMDB has none, so the show is not re-synced', function () {
    config(['services.tmdb.token' => 'test-token']);
    Http::fake([
        'https://api.themoviedb.org/3/tv/1408/season/*' => Http::response(['episodes' => []]),
        'https://api.themoviedb.org/3/tv/1408*' => Http::response(['id' => 1408, 'name' => 'House', 'seasons' => []]),
    ]);

    $show = Show::factory()->create(['tvdb_id' => null, 'tmdb_id' => 1408, 'overview' => null]);

    $this->artisan('shows:sync')->assertSuccessful();

    expect($show->fresh()->overview)->toBe('');
});

it('skips a show missing on TMDB and keeps syncing the others', function () {
    config(['services.tmdb.token' => 'test-token']);

    Http::fake([
        'https://api.themoviedb.org/3/tv/1/*' => Http::response([], 404),
        'https://api.themoviedb.org/3/tv/1?*' => Http::response([], 404),
        'https://api.themoviedb.org/3/tv/2/season/*' => Http::response(['episodes' => [['id' => 9, 'episode_number' => 1, 'name' => 'Ep', 'overview' => 'x']]]),
        'https://api.themoviedb.org/3/tv/2*' => Http::response(['id' => 2, 'name' => 'Alive', 'overview' => 'ok', 'seasons' => [['season_number' => 1]]]),
    ]);

    Show::factory()->create(['tmdb_id' => 1, 'overview' => null]);
    $alive = Show::factory()->create(['tmdb_id' => 2, 'overview' => null]);

    $this->artisan('shows:sync')->assertSuccessful();

    expect($alive->refresh()->name)->toBe('Alive')
        ->and($alive->episodes()->count())->toBe(1);
    Http::assertSentCount(3);
});

it('skips the episode download for ended shows already synced', function () {
    config(['services.tmdb.token' => 'test-token']);

    Http::fake([
        'https://api.themoviedb.org/3/tv/3/season/*' => Http::response(['episodes' => [['episode_number' => 2, 'name' => 'New', 'overview' => 'x']]]),
        'https://api.themoviedb.org/3/tv/3*' => Http::response(['id' => 3, 'name' => 'Done', 'overview' => 'ok', 'status' => 'Ended', 'seasons' => [['season_number' => 1]]]),
    ]);

    $show = Show::factory()->create(['tmdb_id' => 3, 'status' => 'Ended']);
    Episode::factory()->create(['show_id' => $show->id, 'season_number' => 1, 'episode_number' => 1]);

    $this->artisan('shows:sync', ['--all' => true])->assertSuccessful();

    expect($show->refresh()->name)->toBe('Done')
        ->and($show->episodes()->count())->toBe(1);
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/season/'));
});

it('still downloads episodes for an ended show synced for the first time', function () {
    config(['services.tmdb.token' => 'test-token']);

    Http::fake([
        'https://api.themoviedb.org/3/tv/4/season/*' => Http::response(['episodes' => [['episode_number' => 1, 'name' => 'Ep', 'overview' => 'x']]]),
        'https://api.themoviedb.org/3/tv/4*' => Http::response(['id' => 4, 'name' => 'Old', 'overview' => 'ok', 'status' => 'Ended', 'seasons' => [['season_number' => 1]]]),
    ]);

    $show = Show::factory()->create(['tmdb_id' => 4, 'status' => 'Ended', 'overview' => null]);

    $this->artisan('shows:sync')->assertSuccessful();

    expect($show->episodes()->count())->toBe(1);
});

it('downloads all seasons of a show', function () {
    config(['services.tmdb.token' => 'test-token']);

    Http::fake([
        'https://api.themoviedb.org/3/tv/5/season/1*' => Http::response(['episodes' => [['episode_number' => 1, 'name' => 'A', 'overview' => 'x']]]),
        'https://api.themoviedb.org/3/tv/5/season/2*' => Http::response(['episodes' => [['episode_number' => 1, 'name' => 'B', 'overview' => 'x'], ['episode_number' => 2, 'name' => 'C', 'overview' => 'x']]]),
        'https://api.themoviedb.org/3/tv/5/season/3*' => Http::response([], 404),
        'https://api.themoviedb.org/3/tv/5*' => Http::response(['id' => 5, 'name' => 'Multi', 'overview' => 'ok', 'seasons' => [
            ['season_number' => 1], ['season_number' => 2], ['season_number' => 3],
        ]]),
    ]);

    $show = Show::factory()->create(['tmdb_id' => 5, 'overview' => null]);

    $this->artisan('shows:sync')->assertSuccessful();

    expect($show->episodes()->where('season_number', 1)->count())->toBe(1)
        ->and($show->episodes()->where('season_number', 2)->count())->toBe(2);
});
