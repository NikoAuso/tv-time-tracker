<?php

use App\Models\Episode;
use App\Models\Movie;
use App\Models\Show;
use App\Models\User;
use App\Models\UserList;
use App\Models\UserMovie;
use App\Models\UserShow;
use App\Models\WatchedEpisode;
use App\Services\UserData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function seedLibrary(User $user): void
{
    $show = Show::factory()->create(['tmdb_id' => 100, 'name' => 'House']);
    UserShow::factory()->create([
        'user_id' => $user->id, 'show_id' => $show->id,
        'status' => 'following', 'is_favorite' => true, 'rating' => 5,
    ]);
    $ep = Episode::factory()->create(['show_id' => $show->id, 'season_number' => 1, 'episode_number' => 1, 'runtime' => 45]);
    WatchedEpisode::factory()->create(['user_id' => $user->id, 'episode_id' => $ep->id]);

    $movie = Movie::factory()->create(['tmdb_id' => 200, 'title' => 'Fury']);
    UserMovie::factory()->create(['user_id' => $user->id, 'movie_id' => $movie->id, 'status' => 'watched', 'rating' => 4]);

    $list = UserList::factory()->create(['user_id' => $user->id, 'name' => 'Preferite']);
    $list->shows()->attach($show->id);
    $list->movies()->attach($movie->id);
}

it('exports and re-imports the full library into another user', function () {
    $source = User::factory()->create();
    seedLibrary($source);

    $export = UserData::export($source->id);

    $target = User::factory()->create();
    UserData::import($target->id, $export);

    expect(UserShow::where('user_id', $target->id)->where('status', 'following')->where('is_favorite', true)->where('rating', 5)->count())->toBe(1)
        ->and(WatchedEpisode::where('user_id', $target->id)->count())->toBe(1)
        ->and(UserMovie::where('user_id', $target->id)->where('status', 'watched')->where('rating', 4)->count())->toBe(1);

    $list = UserList::where('user_id', $target->id)->where('name', 'Preferite')->first();
    expect($list)->not->toBeNull()
        ->and($list->shows()->count())->toBe(1)
        ->and($list->movies()->count())->toBe(1);

    // Serie e film sono condivisi per tmdb_id, non duplicati.
    expect(Show::where('tmdb_id', 100)->count())->toBe(1)
        ->and(Movie::where('tmdb_id', 200)->count())->toBe(1);
});

it('imports idempotently without creating duplicates', function () {
    $user = User::factory()->create();
    $data = [
        'app' => 'tv-time-tracker',
        'version' => 1,
        'shows' => [[
            'tmdb_id' => 100, 'name' => 'House', 'status' => 'following', 'is_favorite' => false,
            'watched_episodes' => [['season' => 1, 'episode' => 1, 'runtime' => 45]],
        ]],
        'movies' => [['tmdb_id' => 200, 'title' => 'Fury', 'status' => 'watched']],
        'lists' => [['name' => 'Da recuperare', 'shows' => [100], 'movies' => [200]]],
    ];

    UserData::import($user->id, $data);
    UserData::import($user->id, $data);

    expect(UserShow::where('user_id', $user->id)->count())->toBe(1)
        ->and(WatchedEpisode::where('user_id', $user->id)->count())->toBe(1)
        ->and(UserMovie::where('user_id', $user->id)->count())->toBe(1)
        ->and(UserList::where('user_id', $user->id)->count())->toBe(1);

    $list = UserList::where('user_id', $user->id)->first();
    expect($list->shows()->count())->toBe(1)->and($list->movies()->count())->toBe(1);
});

it('does not overwrite a shared catalog show on import', function () {
    Show::factory()->create(['tmdb_id' => 100, 'name' => 'House', 'poster_path' => '/real.jpg']);
    $user = User::factory()->create();

    UserData::import($user->id, [
        'app' => 'tv-time-tracker',
        'version' => 1,
        'shows' => [['tmdb_id' => 100, 'name' => 'Hacked', 'poster_path' => '/evil.jpg', 'status' => 'following']],
    ]);

    $show = Show::where('tmdb_id', 100)->first();
    expect($show->name)->toBe('House')->and($show->poster_path)->toBe('/real.jpg')
        ->and(Show::where('tmdb_id', 100)->count())->toBe(1);
});

it('sanitizes untrusted status and rating on import', function () {
    $user = User::factory()->create();

    UserData::import($user->id, [
        'app' => 'tv-time-tracker',
        'version' => 1,
        'shows' => [['tmdb_id' => 100, 'name' => 'House', 'status' => 'garbage', 'rating' => 9999]],
        'movies' => [['tmdb_id' => 200, 'title' => 'Fury', 'status' => 'hacked', 'rating' => -3]],
    ]);

    $us = UserShow::where('user_id', $user->id)->first();
    expect($us->status)->toBe('watchlist')->and($us->rating)->toBeNull();

    $um = UserMovie::where('user_id', $user->id)->first();
    expect($um->status)->toBe('watchlist')->and($um->rating)->toBeNull();
});

it('downloads a JSON export from the settings page', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test('pages::settings.import')
        ->call('exportJson')
        ->assertFileDownloaded('tv-time-tracker-'.now()->format('Y-m-d').'.json');
});

it('imports a TvTimeTracker JSON backup from the settings page', function () {
    $source = User::factory()->create();
    seedLibrary($source);
    $json = (string) json_encode(UserData::export($source->id));

    // Utente senza token: isola l'import dei dati dalla sync TMDB (coperta altrove).
    $target = User::factory()->withoutTmdbToken()->create();

    Livewire::actingAs($target)->test('pages::settings.import')
        ->call('importJson', 'backup.json', base64_encode($json))
        ->assertHasNoErrors();

    expect(UserShow::where('user_id', $target->id)->count())->toBe(1)
        ->and(WatchedEpisode::where('user_id', $target->id)->count())->toBe(1);
});

it('syncs episodes from TMDB after a JSON import so "up next" is populated', function () {
    Http::fake([
        'https://api.themoviedb.org/3/tv/100/season/*' => Http::response(['episodes' => [
            ['id' => 11, 'episode_number' => 1, 'name' => 'Pilot', 'air_date' => '2020-01-01', 'runtime' => 45],
            ['id' => 12, 'episode_number' => 2, 'name' => 'Secondo', 'air_date' => '2020-01-08', 'runtime' => 45],
        ]]),
        'https://api.themoviedb.org/3/tv/100*' => Http::response([
            'id' => 100, 'name' => 'House', 'overview' => 'Un medico scontroso.',
            'poster_path' => '/h.jpg', 'first_air_date' => '2020-01-01',
            'number_of_episodes' => 2, 'status' => 'Ended', 'genres' => [],
            'seasons' => [['season_number' => 1]],
        ]),
        '*' => Http::response([]),
    ]);

    $source = User::factory()->create();
    seedLibrary($source); // serie tmdb_id=100 con un solo episodio visto (S1E1)
    $json = (string) json_encode(UserData::export($source->id));

    $target = User::factory()->create();

    Livewire::actingAs($target)->test('pages::settings.import')
        ->call('importJson', 'backup.json', base64_encode($json))
        ->assertHasNoErrors();

    // La sync scarica l'intera lista episodi, non solo quello visto dal backup.
    $show = Show::where('tmdb_id', 100)->firstOrFail();
    expect(Episode::where('show_id', $show->id)->count())->toBe(2);

    // L'episodio non visto (S1E2) ora compare in "Serie da vedere".
    $this->actingAs($target)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('House')
        ->assertDontSee('Sei in pari');
});

it('rejects a JSON file that is not a TvTimeTracker backup', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test('pages::settings.import')
        ->call('importJson', 'other.json', base64_encode('{"app":"something-else"}'))
        ->assertHasErrors('jsonFile');

    expect(UserShow::where('user_id', $user->id)->count())->toBe(0);
});

it('wipes all data for the current user only, keeping the shared catalog', function () {
    $show = Show::factory()->create();
    $movie = Movie::factory()->create();
    $episode = Episode::factory()->create(['show_id' => $show->id]);

    $me = User::factory()->create();
    $other = User::factory()->create();

    foreach ([$me, $other] as $u) {
        UserShow::factory()->create(['user_id' => $u->id, 'show_id' => $show->id, 'status' => 'following']);
        UserMovie::factory()->create(['user_id' => $u->id, 'movie_id' => $movie->id]);
        WatchedEpisode::factory()->create(['user_id' => $u->id, 'episode_id' => $episode->id]);
        UserList::factory()->create(['user_id' => $u->id, 'name' => 'Preferite']);
    }

    UserData::wipe($me->id);

    expect(UserShow::where('user_id', $me->id)->count())->toBe(0)
        ->and(UserMovie::where('user_id', $me->id)->count())->toBe(0)
        ->and(WatchedEpisode::where('user_id', $me->id)->count())->toBe(0)
        ->and(UserList::where('user_id', $me->id)->count())->toBe(0)
        // dati dell'altro utente e catalogo condiviso intatti
        ->and(UserShow::where('user_id', $other->id)->count())->toBe(1)
        ->and(WatchedEpisode::where('user_id', $other->id)->count())->toBe(1)
        ->and(Show::count())->toBe(1)
        ->and(Movie::count())->toBe(1)
        ->and(Episode::count())->toBe(1);
});

it('wipes data from the settings page', function () {
    $user = User::factory()->create();
    seedLibrary($user);

    Livewire::actingAs($user)->test('pages::settings.import')
        ->call('wipeData')
        ->assertHasNoErrors();

    expect(UserShow::where('user_id', $user->id)->count())->toBe(0)
        ->and(WatchedEpisode::where('user_id', $user->id)->count())->toBe(0)
        ->and(UserList::where('user_id', $user->id)->count())->toBe(0);
});
