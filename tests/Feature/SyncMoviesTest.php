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
