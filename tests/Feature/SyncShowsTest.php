<?php

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
