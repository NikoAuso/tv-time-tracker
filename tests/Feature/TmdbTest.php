<?php

use App\Services\Tmdb;
use Illuminate\Support\Facades\Http;

it('requests italian localized data from tmdb', function () {
    config(['services.tmdb.token' => 'fake-token']);
    Http::fake(['*' => Http::response([], 200)]);

    app(Tmdb::class)->getShow(123);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'language=it-IT'));
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
