<?php

use App\Models\Episode;
use App\Models\Movie;
use App\Models\Show;
use App\Models\User;
use App\Models\UserMovie;
use App\Models\UserShow;
use App\Models\WatchedEpisode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function writeExtExport(array $series, array $movies): string
{
    $dir = sys_get_temp_dir().'/ext_'.uniqid();
    mkdir($dir);
    file_put_contents($dir.'/tvtime-series-2026.json', json_encode($series));
    file_put_contents($dir.'/tvtime-movies-2026.json', json_encode($movies));

    return $dir;
}

it('imports series, episodes and watches from the extension json', function () {
    config(['services.tmdb.token' => 'fake']);
    $user = User::factory()->create();

    $dir = writeExtExport([[
        'id' => ['tvdb' => 411796, 'imdb' => null],
        'title' => 'The Blackout',
        'status' => 'up_to_date',
        'is_favorite' => true,
        'created_at' => '2024-09-23T18:07:38Z',
        'seasons' => [[
            'number' => 1, 'is_specials' => false,
            'episodes' => [
                ['id' => ['tvdb' => 1], 'number' => 1, 'name' => 'Ep1', 'is_watched' => true, 'watched_at' => '2024-09-23T18:07:39Z'],
                ['id' => ['tvdb' => 2], 'number' => 2, 'name' => 'Ep2', 'is_watched' => false],
            ],
        ]],
    ]], []);

    Artisan::call('import:tvtime-json', ['path' => $dir, '--user' => $user->id]);

    $show = Show::where('tvdb_id', 411796)->first();
    expect($show)->not->toBeNull()
        ->and($show->name)->toBe('The Blackout');
    expect(Episode::where('show_id', $show->id)->count())->toBe(1); // solo l'episodio visto
    expect(WatchedEpisode::where('user_id', $user->id)->count())->toBe(1);

    $us = UserShow::where(['user_id' => $user->id, 'show_id' => $show->id])->first();
    expect($us->status)->toBe('following')
        ->and((bool) $us->is_favorite)->toBeTrue();
});

it('imports movies resolving tmdb from the imdb id', function () {
    config(['services.tmdb.token' => 'fake']);
    Http::fake(['*/find/tt0163025*' => Http::response(['movie_results' => [[
        'id' => 330, 'title' => 'Jurassic Park III', 'release_date' => '2001-07-18',
        'poster_path' => '/jp3.jpg', 'overview' => 'Dinosauri.',
    ]]])]);
    $user = User::factory()->create();

    $dir = writeExtExport([], [[
        'id' => ['tvdb' => 1, 'imdb' => 'tt0163025'],
        'title' => 'Jurassic Park III', 'year' => 2001,
        'watched_at' => '2024-09-22T16:55:32Z', 'is_watched' => true,
        'rewatch_count' => 1, 'is_favorite' => false,
    ]]);

    Artisan::call('import:tvtime-json', ['path' => $dir, '--user' => $user->id]);

    $movie = Movie::where('imdb_id', 'tt0163025')->first();
    expect($movie)->not->toBeNull()
        ->and($movie->tmdb_id)->toBe(330)
        ->and($movie->title)->toBe('Jurassic Park III')
        ->and($movie->poster_path)->toBe('/jp3.jpg');

    $um = UserMovie::where(['user_id' => $user->id, 'movie_id' => $movie->id])->first();
    expect($um->status)->toBe('watched')
        ->and($um->rewatch_count)->toBe(1);
});

it('converges csv and extension movies on the same tmdb id', function () {
    config(['services.tmdb.token' => 'fake']);
    Http::fake([
        '*/find/tt0163025*' => Http::response(['movie_results' => [['id' => 330, 'title' => 'Jurassic Park III']]]),
        '*/search/movie*' => Http::response(['results' => [['id' => 330, 'title' => 'Jurassic Park III', 'release_date' => '2001-07-18']]]),
    ]);
    $user = User::factory()->create();

    // Import CSV: il film è identificato da tvtime_uuid, il tmdb_id è risolto per titolo+anno.
    $csvDir = sys_get_temp_dir().'/csv_'.uniqid();
    mkdir($csvDir);
    file_put_contents($csvDir.'/tracking-prod-records-v2.csv', "key,s_id,series_name\n");
    file_put_contents(
        $csvDir.'/tracking-prod-records.csv',
        "uuid,type,entity_type,movie_name,release_date,runtime,updated_at\n"
        ."abc,watch,movie,Jurassic Park III,2001-07-18 00:00:00,5460,2024-01-01 00:00:00\n",
    );
    Artisan::call('import:tvtime', ['path' => $csvDir, '--user' => $user->id]);

    // Import estensione: stesso film per imdb_id → risolve lo stesso tmdb_id → fonde.
    $jsonDir = writeExtExport([], [[
        'id' => ['imdb' => 'tt0163025'], 'title' => 'Jurassic Park III', 'year' => 2001,
        'is_watched' => true, 'watched_at' => '2024-01-01T00:00:00Z',
    ]]);
    Artisan::call('import:tvtime-json', ['path' => $jsonDir, '--user' => $user->id]);

    expect(Movie::count())->toBe(1);
    $movie = Movie::first();
    expect($movie->tmdb_id)->toBe(330)
        ->and($movie->imdb_id)->toBe('tt0163025')
        ->and($movie->tvtime_uuid)->toBe('abc');
});

it('is idempotent on re-import', function () {
    config(['services.tmdb.token' => 'fake']);
    Http::fake(['*/find/*' => Http::response(['movie_results' => [['id' => 330]]])]);
    $user = User::factory()->create();

    $dir = writeExtExport(
        [['id' => ['tvdb' => 10], 'title' => 'S', 'seasons' => [['number' => 1, 'episodes' => [
            ['number' => 1, 'is_watched' => true, 'watched_at' => '2024-01-01T00:00:00Z'],
        ]]]]],
        [['id' => ['imdb' => 'tt1'], 'title' => 'M', 'year' => 2020, 'is_watched' => true, 'watched_at' => '2024-01-01T00:00:00Z']],
    );

    Artisan::call('import:tvtime-json', ['path' => $dir, '--user' => $user->id]);
    Artisan::call('import:tvtime-json', ['path' => $dir, '--user' => $user->id]);

    expect(Show::count())->toBe(1)
        ->and(Movie::count())->toBe(1)
        ->and(WatchedEpisode::count())->toBe(1)
        ->and(UserMovie::count())->toBe(1);
});
