<?php

use App\Models\Episode;
use App\Models\Show;
use App\Models\User;
use App\Models\UserMovie;
use App\Models\UserShow;
use App\Models\WatchedEpisode;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function fakeExport(array $rows): string
{
    $dir = sys_get_temp_dir().'/tvtime_'.uniqid();
    mkdir($dir);
    $handle = fopen($dir.'/tracking-prod-records-v2.csv', 'w');
    fputcsv($handle, ['key', 's_id', 'series_name', 'is_followed', 'is_for_later', 'is_archived', 'followed_at', 'season_number', 'episode_number', 'updated_at']);
    foreach ($rows as $row) {
        fputcsv($handle, $row);
    }
    fclose($handle);

    return $dir;
}

it('imports shows, library status, episodes and watches', function () {
    $user = User::factory()->create();

    $dir = fakeExport([
        ['user-series-a', 73255, 'House', 'true', 'false', 'false', '1727115166066390', '', '', ''],
        ['user-series-b', 999, 'Old Show', 'false', 'false', 'true', '', '', '', ''],
        ['watch-episode-1', 73255, 'House', '', '', '', '', 1, 1, '2024-09-23 18:12:46'],
        ['watch-episode-2', 73255, 'House', '', '', '', '', 1, 2, '2024-09-24 10:00:00'],
    ]);

    $this->artisan('import:tvtime', ['path' => $dir, '--user' => $user->id])
        ->assertSuccessful();

    expect(Show::count())->toBe(2)
        ->and(Episode::count())->toBe(2)
        ->and(WatchedEpisode::count())->toBe(2)
        ->and(UserShow::where('status', 'following')->count())->toBe(1)
        ->and(UserShow::where('status', 'archived')->count())->toBe(1);

    $house = Show::where('tvdb_id', 73255)->first();
    expect($house->name)->toBe('House');
    expect(WatchedEpisode::first()->watched_at->toDateString())->toBe('2024-09-23');
});

it('imports a watched movie with its watch date', function () {
    $user = User::factory()->create();
    $dir = fakeExport([]);

    $handle = fopen($dir.'/tracking-prod-records.csv', 'w');
    fputcsv($handle, ['uuid', 'movie_name', 'release_date', 'runtime', 'type', 'entity_type', 'updated_at']);
    fputcsv($handle, ['m-1', 'Fury', '2014-10-15', '8040', 'watch', 'movie', '2024-09-22 16:52:34']);
    fclose($handle);

    $this->artisan('import:tvtime', ['path' => $dir, '--user' => $user->id])->assertSuccessful();

    $entry = UserMovie::first();
    expect($entry->status)->toBe('watched')
        ->and($entry->watched_at)->not->toBeNull()
        ->and($entry->watched_at->toDateString())->toBe('2024-09-22');
});

it('is idempotent when run twice', function () {
    $user = User::factory()->create();
    $dir = fakeExport([
        ['user-series-a', 73255, 'House', 'true', 'false', 'false', '', '', '', ''],
        ['watch-episode-1', 73255, 'House', '', '', '', '', 1, 1, '2024-09-23 18:12:46'],
    ]);

    $this->artisan('import:tvtime', ['path' => $dir, '--user' => $user->id])->assertSuccessful();
    $this->artisan('import:tvtime', ['path' => $dir, '--user' => $user->id])->assertSuccessful();

    expect(Show::count())->toBe(1)
        ->and(Episode::count())->toBe(1)
        ->and(WatchedEpisode::count())->toBe(1)
        ->and(UserShow::count())->toBe(1);
});
