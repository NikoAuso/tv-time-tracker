<?php

use App\Models\Episode;
use App\Models\Show;
use App\Models\User;
use App\Models\WatchedEpisode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function showWithEpisodes(array $specs): Show
{
    $show = Show::factory()->create();
    foreach ($specs as [$s, $e]) {
        Episode::factory()->create(['show_id' => $show->id, 'season_number' => $s, 'episode_number' => $e]);
    }

    return $show;
}

it('shows genres, trailer, providers and season episodes on the detail page', function () {
    config(['services.tmdb.token' => 'fake-token']);
    Http::fake([
        '*/watch/providers*' => Http::response(['results' => ['IT' => [
            'link' => 'https://justwatch/it',
            'flatrate' => [['provider_name' => 'Netflix', 'logo_path' => '/n.jpg']],
        ]]]),
        '*/videos*' => Http::response(['results' => [
            ['site' => 'YouTube', 'type' => 'Trailer', 'key' => 'abc123'],
        ]]),
    ]);

    $user = User::factory()->create();
    $show = Show::factory()->create(['tmdb_id' => 42, 'genres' => ['Dramma', 'Mistero'], 'status' => 'Ended', 'overview' => 'Una trama avvincente.']);
    Episode::factory()->create(['show_id' => $show->id, 'season_number' => 1, 'episode_number' => 1, 'name' => 'Pilot']);

    Livewire::actingAs($user)->test('pages::show', ['show' => $show])
        ->assertSee('Dramma')
        ->assertSee('Mistero')
        ->assertSee('Trama')
        ->assertSee('Dove guardarlo')
        ->assertSee('Conclusa')
        ->assertSee('Pilot')
        ->assertSee('youtube.com/watch?v=abc123', false);
});

it('marks a whole season watched', function () {
    $user = User::factory()->create();
    $show = showWithEpisodes([[1, 1], [1, 2], [2, 1]]);

    Livewire::actingAs($user)->test('pages::show', ['show' => $show])
        ->call('markSeason', 1);

    expect(WatchedEpisode::where('user_id', $user->id)->count())->toBe(2);
    $s2 = Episode::where(['show_id' => $show->id, 'season_number' => 2, 'episode_number' => 1])->first();
    expect(WatchedEpisode::where('episode_id', $s2->id)->exists())->toBeFalse();
});

it('marks all episodes up to a given one', function () {
    $user = User::factory()->create();
    $show = showWithEpisodes([[1, 1], [1, 2], [2, 1], [2, 2]]);
    $target = Episode::where(['show_id' => $show->id, 'season_number' => 2, 'episode_number' => 1])->first();

    Livewire::actingAs($user)->test('pages::show', ['show' => $show])
        ->call('markUpTo', $target->id);

    expect(WatchedEpisode::where('user_id', $user->id)->count())->toBe(3);
    $last = Episode::where(['show_id' => $show->id, 'season_number' => 2, 'episode_number' => 2])->first();
    expect(WatchedEpisode::where('episode_id', $last->id)->exists())->toBeFalse();
});

it('does not double-count when marking a season twice', function () {
    $user = User::factory()->create();
    $show = showWithEpisodes([[1, 1], [1, 2]]);

    Livewire::actingAs($user)->test('pages::show', ['show' => $show])
        ->call('markSeason', 1)
        ->call('markSeason', 1);

    expect(WatchedEpisode::where('user_id', $user->id)->count())->toBe(2);
});

it('renders the episode detail and toggles it watched', function () {
    $user = User::factory()->create();
    $show = Show::factory()->create(['name' => 'House']);
    $episode = Episode::factory()->create([
        'show_id' => $show->id, 'season_number' => 1, 'episode_number' => 1,
        'name' => 'Pilot', 'overview' => 'A diagnostic mystery.',
    ]);

    $this->actingAs($user)->get(route('episodes.show', $episode))
        ->assertOk()
        ->assertSee('Pilot')
        ->assertSee('A diagnostic mystery.');

    Livewire::actingAs($user)->test('pages::episode', ['episode' => $episode])
        ->call('toggle');
    expect(WatchedEpisode::where(['user_id' => $user->id, 'episode_id' => $episode->id])->exists())->toBeTrue();
});
