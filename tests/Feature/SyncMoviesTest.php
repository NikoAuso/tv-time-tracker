<?php

use App\Models\Movie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('enriches a movie from TMDB by title and year', function () {
    config(['services.tmdb.token' => 'test-token']);

    Http::fake([
        'https://api.themoviedb.org/3/search/movie*' => Http::response(['results' => [[
            'id' => 16869,
            'title' => 'Fury',
            'poster_path' => '/fury.jpg',
            'overview' => 'A tank crew in WWII.',
            'release_date' => '2014-10-17',
        ]]]),
        'https://api.themoviedb.org/3/movie/16869*' => Http::response([
            'id' => 16869,
            'genres' => [['id' => 10752, 'name' => 'Guerra'], ['id' => 18, 'name' => 'Dramma']],
        ]),
    ]);

    $movie = Movie::factory()->create(['tmdb_id' => null, 'title' => 'Fury', 'release_date' => '2014-10-17']);

    $this->artisan('movies:sync')->assertSuccessful();

    $movie->refresh();
    expect($movie->tmdb_id)->toBe(16869)
        ->and($movie->poster_path)->toBe('/fury.jpg')
        ->and($movie->overview)->toBe('A tank crew in WWII.')
        ->and($movie->genres)->toBe(['Guerra', 'Dramma']);
});

it('picks the closest year when the stored date drifts from TMDB', function () {
    config(['services.tmdb.token' => 'test-token']);

    // La data importata (2014) è sfasata rispetto al primary_release_year (2013).
    Http::fake([
        'https://api.themoviedb.org/3/search/movie*' => Http::response(['results' => [
            ['id' => 999, 'title' => 'The Wolf of Wall Street', 'poster_path' => '/wrong.jpg', 'release_date' => '2020-01-01'],
            ['id' => 106646, 'title' => 'The Wolf of Wall Street', 'poster_path' => '/right.jpg', 'release_date' => '2013-12-25'],
        ]]),
        'https://api.themoviedb.org/3/movie/106646*' => Http::response(['id' => 106646, 'genres' => []]),
    ]);

    $movie = Movie::factory()->create(['tmdb_id' => null, 'title' => 'The Wolf of Wall Street', 'release_date' => '2014-01-24']);

    $this->artisan('movies:sync')->assertSuccessful();

    $movie->refresh();
    expect($movie->tmdb_id)->toBe(106646)
        ->and($movie->poster_path)->toBe('/right.jpg');
});

it('leaves a movie unresolved when TMDB has no match', function () {
    config(['services.tmdb.token' => 'test-token']);
    Http::fake(['https://api.themoviedb.org/3/search/movie*' => Http::response(['results' => []])]);

    $movie = Movie::factory()->create(['tmdb_id' => null]);

    $this->artisan('movies:sync')->assertSuccessful();

    expect($movie->fresh()->tmdb_id)->toBeNull();
});

it('fails without a TMDB token', function () {
    config(['services.tmdb.token' => null]);
    Movie::factory()->create(['tmdb_id' => null]);

    $this->artisan('movies:sync')->assertFailed();
});

it('enriches an imported movie by its tmdb_id without re-matching by title', function () {
    config(['services.tmdb.token' => 'test-token']);

    Http::fake([
        'https://api.themoviedb.org/3/movie/16869*' => Http::response([
            'id' => 16869, 'title' => 'Fury', 'poster_path' => '/fury.jpg',
            'overview' => 'A tank crew in WWII.', 'release_date' => '2014-10-17',
            'runtime' => 134, 'genres' => [['id' => 18, 'name' => 'Dramma']],
        ]),
        'https://api.themoviedb.org/3/search/movie*' => Http::response(['results' => []]),
    ]);

    // Film da backup JSON: ha già tmdb_id ma nessuna trama.
    $movie = Movie::factory()->create(['tmdb_id' => 16869, 'title' => 'Fury', 'overview' => null]);

    $this->artisan('movies:sync')->assertSuccessful();

    expect($movie->fresh()->overview)->toBe('A tank crew in WWII.')
        ->and($movie->fresh()->genres)->toBe(['Dramma']);
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/search/movie'));
});

it('stores an empty overview when TMDB has none, so the movie is not re-synced', function () {
    config(['services.tmdb.token' => 'test-token']);
    Http::fake(['https://api.themoviedb.org/3/movie/500*' => Http::response(['id' => 500, 'title' => 'Obscure', 'genres' => []])]);

    $movie = Movie::factory()->create(['tmdb_id' => 500, 'title' => 'Obscure', 'overview' => null]);

    $this->artisan('movies:sync')->assertSuccessful();

    expect($movie->fresh()->overview)->toBe('');
});
