<?php

use App\Models\Episode;
use App\Models\Movie;
use App\Models\Show;
use App\Models\User;
use App\Models\UserMovie;
use App\Models\UserShow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('shows a token notice when no TMDB token is set', function () {
    config(['services.tmdb.token' => null]);

    Livewire::actingAs(User::factory()->withoutTmdbToken()->create())->test('pages::search')
        ->assertSee('token TMDB');
});

it('finds shows and adds one (with episodes) to the library', function () {
    config(['services.tmdb.token' => 'fake-token']);
    Http::fake([
        'https://api.themoviedb.org/3/trending/*' => Http::response(['results' => []]),
        'https://api.themoviedb.org/3/search/tv*' => Http::response(['results' => [
            ['id' => 100, 'name' => 'Dune: Prophecy', 'poster_path' => '/d.jpg', 'first_air_date' => '2024-11-17'],
        ]]),
        'https://api.themoviedb.org/3/tv/100/season/*' => Http::response(['episodes' => [
            ['id' => 1, 'episode_number' => 1, 'name' => 'Pilot', 'runtime' => 55],
        ]]),
        'https://api.themoviedb.org/3/tv/100*' => Http::response([
            'id' => 100, 'name' => 'Dune: Prophecy', 'poster_path' => '/d.jpg', 'first_air_date' => '2024-11-17',
            'number_of_episodes' => 1, 'status' => 'Returning Series', 'seasons' => [['season_number' => 1]],
        ]),
    ]);

    $user = User::factory()->create();

    Livewire::actingAs($user)->test('pages::search')
        ->set('type', 'series')
        ->set('q', 'dune')
        ->assertSee('Dune: Prophecy')
        ->call('add', 100)
        ->assertHasNoErrors();

    $show = Show::where('tmdb_id', 100)->first();
    expect($show)->not->toBeNull()
        ->and($show->name)->toBe('Dune: Prophecy')
        ->and(UserShow::where('user_id', $user->id)->where('show_id', $show->id)->value('status'))->toBe('watchlist')
        ->and(Episode::where('show_id', $show->id)->count())->toBe(1);
});

it('finds movies and adds one to the library', function () {
    config(['services.tmdb.token' => 'fake-token']);
    Http::fake([
        'https://api.themoviedb.org/3/trending/*' => Http::response(['results' => []]),
        'https://api.themoviedb.org/3/search/movie*' => Http::response(['results' => [
            ['id' => 200, 'title' => 'Fury', 'poster_path' => '/f.jpg', 'release_date' => '2014-10-15'],
        ]]),
        'https://api.themoviedb.org/3/movie/200*' => Http::response([
            'id' => 200, 'title' => 'Fury', 'poster_path' => '/f.jpg', 'release_date' => '2014-10-15', 'runtime' => 134,
        ]),
    ]);

    $user = User::factory()->create();

    Livewire::actingAs($user)->test('pages::search')
        ->set('type', 'movies')
        ->set('q', 'fury')
        ->assertSee('Fury')
        ->call('add', 200)
        ->assertHasNoErrors();

    $movie = Movie::where('tmdb_id', 200)->first();
    expect($movie)->not->toBeNull()
        ->and($movie->runtime)->toBe(134)
        ->and(UserMovie::where('user_id', $user->id)->where('movie_id', $movie->id)->value('status'))->toBe('watchlist');
});

it('shows trending series and movies on the empty search state', function () {
    config(['services.tmdb.token' => 'fake-token']);
    Http::fake([
        'https://api.themoviedb.org/3/trending/tv/week*' => Http::response(['results' => [
            ['id' => 300, 'name' => 'Trending Show', 'poster_path' => '/t.jpg', 'first_air_date' => '2025-01-01'],
        ]]),
        'https://api.themoviedb.org/3/trending/movie/week*' => Http::response(['results' => [
            ['id' => 400, 'title' => 'Trending Movie', 'poster_path' => '/m.jpg', 'release_date' => '2025-02-02'],
        ]]),
    ]);

    Livewire::actingAs(User::factory()->create())->test('pages::search')
        ->assertSee('Serie di tendenza')
        ->assertSee('Trending Show')
        ->assertSee('Film di tendenza')
        ->assertSee('Trending Movie')
        ->assertSee('Sfoglia tutte le serie')
        ->assertSee('Sfoglia tutti i film');
});

it('adds a trending movie regardless of the active type', function () {
    config(['services.tmdb.token' => 'fake-token']);
    Http::fake([
        'https://api.themoviedb.org/3/trending/tv/week*' => Http::response(['results' => []]),
        'https://api.themoviedb.org/3/trending/movie/week*' => Http::response(['results' => [
            ['id' => 400, 'title' => 'Trending Movie', 'poster_path' => '/m.jpg', 'release_date' => '2025-02-02'],
        ]]),
        'https://api.themoviedb.org/3/movie/400*' => Http::response([
            'id' => 400, 'title' => 'Trending Movie', 'poster_path' => '/m.jpg', 'release_date' => '2025-02-02', 'runtime' => 120,
        ]),
    ]);

    $user = User::factory()->create();

    Livewire::actingAs($user)->test('pages::search')
        ->assertSet('type', 'series')
        ->call('add', 400, 'movies')
        ->assertHasNoErrors();

    expect(UserMovie::where('user_id', $user->id)->whereHas('movie', fn ($q) => $q->where('tmdb_id', 400))->exists())
        ->toBeTrue();
});

it('opens a series from search, downloading it to the catalog without adding it to the library', function () {
    config(['services.tmdb.token' => 'fake-token']);
    Http::fake([
        'https://api.themoviedb.org/3/trending/*' => Http::response(['results' => []]),
        'https://api.themoviedb.org/3/tv/500/season/*' => Http::response(['episodes' => [['id' => 1, 'episode_number' => 1]]]),
        'https://api.themoviedb.org/3/tv/500*' => Http::response(['id' => 500, 'name' => 'New Show', 'seasons' => [['season_number' => 1]]]),
    ]);

    $user = User::factory()->create();

    Livewire::actingAs($user)->test('pages::search')
        ->call('open', 500, 'series')
        ->assertRedirect();

    expect(Show::where('tmdb_id', 500)->exists())->toBeTrue()
        ->and(UserShow::where('user_id', $user->id)->count())->toBe(0);
});

it('opens a movie from search into the catalog', function () {
    config(['services.tmdb.token' => 'fake-token']);
    Http::fake([
        'https://api.themoviedb.org/3/trending/*' => Http::response(['results' => []]),
        'https://api.themoviedb.org/3/movie/600*' => Http::response(['id' => 600, 'title' => 'New Movie']),
    ]);

    $user = User::factory()->create();

    Livewire::actingAs($user)->test('pages::search')
        ->call('open', 600, 'movies')
        ->assertRedirect();

    expect(Movie::where('tmdb_id', 600)->exists())->toBeTrue()
        ->and(UserMovie::where('user_id', $user->id)->count())->toBe(0);
});

it('opens an already-local show without a TMDB token', function () {
    $user = User::factory()->withoutTmdbToken()->create();
    $show = Show::factory()->create(['tmdb_id' => 700]);

    Livewire::actingAs($user)->test('pages::search')
        ->call('open', 700, 'series')
        ->assertRedirect(route('shows.show', $show));
});

it('marks results already in the library', function () {
    config(['services.tmdb.token' => 'fake-token']);
    Http::fake([
        'https://api.themoviedb.org/3/trending/*' => Http::response(['results' => []]),
        'https://api.themoviedb.org/3/search/tv*' => Http::response(['results' => [
            ['id' => 100, 'name' => 'House', 'poster_path' => '/h.jpg', 'first_air_date' => '2004-11-16'],
        ]]),
    ]);

    $user = User::factory()->create();
    $show = Show::factory()->create(['tmdb_id' => 100, 'name' => 'House']);
    UserShow::factory()->create(['user_id' => $user->id, 'show_id' => $show->id]);

    Livewire::actingAs($user)->test('pages::search')
        ->set('type', 'series')
        ->set('q', 'house')
        ->assertSee('In libreria');
});
