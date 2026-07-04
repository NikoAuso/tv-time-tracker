<?php

use App\Services\Tmdb;
use Illuminate\Support\Facades\Http;

it('requests italian localized data from tmdb', function () {
    config(['services.tmdb.token' => 'fake-token']);
    Http::fake(['*' => Http::response([], 200)]);

    app(Tmdb::class)->getShow(123);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'language=it-IT'));
});
