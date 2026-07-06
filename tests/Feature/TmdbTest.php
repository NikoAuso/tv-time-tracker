<?php

use App\Services\Tmdb;
use Illuminate\Support\Facades\Http;

it('requests italian localized data from tmdb', function () {
    config(['services.tmdb.token' => 'fake-token']);
    Http::fake(['*' => Http::response([], 200)]);

    app(Tmdb::class)->getShow(123);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'language=it-IT'));
});

it('fills an empty italian movie overview from english', function () {
    config(['services.tmdb.token' => 'fake-token']);
    Http::fake([
        '*/movie/1?language=en-US*' => Http::response(['id' => 1, 'title' => 'Movie', 'overview' => 'English plot.']),
        '*/movie/1*' => Http::response(['id' => 1, 'title' => 'Film', 'overview' => '']),
    ]);

    $movie = app(Tmdb::class)->getMovie(1);

    expect($movie['overview'])->toBe('English plot.')
        ->and($movie['title'])->toBe('Film');
});

it('fills empty italian show text from english', function () {
    config(['services.tmdb.token' => 'fake-token']);
    Http::fake([
        '*/tv/1?language=en-US*' => Http::response(['id' => 1, 'name' => 'The Show', 'overview' => 'English synopsis.']),
        '*/tv/1*' => Http::response(['id' => 1, 'name' => '', 'overview' => '']),
    ]);

    $show = app(Tmdb::class)->getShow(1);

    expect($show['name'])->toBe('The Show')
        ->and($show['overview'])->toBe('English synopsis.');
});

it('fills empty italian episode text from english', function () {
    config(['services.tmdb.token' => 'fake-token']);
    Http::fake([
        '*/tv/1/season/1?language=en-US*' => Http::response(['episodes' => [
            ['episode_number' => 1, 'name' => 'The Pilot', 'overview' => 'English episode.'],
        ]]),
        '*/tv/1/season/1*' => Http::response(['episodes' => [
            ['episode_number' => 1, 'name' => '', 'overview' => ''],
        ]]),
    ]);

    $episodes = app(Tmdb::class)->getSeasonEpisodes(1, 1);

    expect($episodes[0]['name'])->toBe('The Pilot')
        ->and($episodes[0]['overview'])->toBe('English episode.');
});

it('does not fetch english when italian text is present', function () {
    config(['services.tmdb.token' => 'fake-token']);
    Http::fake(['*/movie/5*' => Http::response(['id' => 5, 'title' => 'Film', 'overview' => 'Trama italiana.'])]);

    app(Tmdb::class)->getMovie(5);

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'language=en-US'));
});

it('prefers an exact title match over a closer-year homonym', function () {
    config(['services.tmdb.token' => 'fake-token']);
    // Un film oscuro con lo stesso anno non deve scavalcare "300" (titolo esatto).
    Http::fake(['*/search/movie*' => Http::response(['results' => [
        ['id' => 999, 'title' => '300 yen', 'original_title' => 'Obscure', 'release_date' => '2006-01-01'],
        ['id' => 1271, 'title' => '300', 'original_title' => '300', 'release_date' => '2007-03-07'],
    ]])]);

    expect(app(Tmdb::class)->searchMovie('300', 2006)['id'])->toBe(1271);
});

it('matches on the original title when the localized title differs', function () {
    config(['services.tmdb.token' => 'fake-token']);
    Http::fake(['*/search/movie*' => Http::response(['results' => [
        ['id' => 420818, 'title' => 'Il re leone', 'original_title' => 'The Lion King', 'release_date' => '2019-07-12'],
        ['id' => 504949, 'title' => 'Il re', 'original_title' => 'The King', 'release_date' => '2019-11-01'],
    ]])]);

    expect(app(Tmdb::class)->searchMovie('The King', 2019)['id'])->toBe(504949);
});

it('extracts flatrate providers for the region', function () {
    config(['services.tmdb.token' => 'fake-token']);
    Http::fake(['*/watch/providers*' => Http::response(['results' => ['IT' => [
        'link' => 'https://justwatch/it',
        'flatrate' => [['provider_name' => 'Netflix', 'logo_path' => '/n.jpg']],
    ]]])]);

    $providers = app(Tmdb::class)->movieProviders(1);

    expect($providers['link'])->toBe('https://justwatch/it')
        ->and($providers['flatrate'])->toBe([['name' => 'Netflix', 'logo_path' => '/n.jpg']]);
});

it('builds a youtube url for the first trailer', function () {
    config(['services.tmdb.token' => 'fake-token']);
    Http::fake(['*/videos*' => Http::response(['results' => [
        ['site' => 'YouTube', 'type' => 'Clip', 'key' => 'clip1'],
        ['site' => 'YouTube', 'type' => 'Trailer', 'key' => 'abc123'],
    ]])]);

    expect(app(Tmdb::class)->movieTrailer(1))->toBe('https://www.youtube.com/watch?v=abc123');
});
