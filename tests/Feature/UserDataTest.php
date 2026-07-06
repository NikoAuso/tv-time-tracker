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
use Illuminate\Http\UploadedFile;
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

    $target = User::factory()->create();

    Livewire::actingAs($target)->test('pages::settings.import')
        ->set('jsonFile', UploadedFile::fake()->createWithContent('backup.json', $json))
        ->call('importJson')
        ->assertHasNoErrors();

    expect(UserShow::where('user_id', $target->id)->count())->toBe(1)
        ->and(WatchedEpisode::where('user_id', $target->id)->count())->toBe(1);
});

it('rejects a JSON file that is not a TvTimeTracker backup', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test('pages::settings.import')
        ->set('jsonFile', UploadedFile::fake()->createWithContent('other.json', '{"app":"something-else"}'))
        ->call('importJson')
        ->assertHasErrors('jsonFile');

    expect(UserShow::where('user_id', $user->id)->count())->toBe(0);
});
