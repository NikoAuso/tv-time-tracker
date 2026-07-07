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

it('marks watched episodes even when an earlier unwatched one exists', function () {
    $user = User::factory()->create();
    $show = Show::factory()->create();
    // Uno speciale (stagione 0) non visto è ordinato per primo nella collection:
    // regressione per each() che si fermava al primo episodio non visto.
    Episode::factory()->create(['show_id' => $show->id, 'season_number' => 0, 'episode_number' => 1]);
    $watched = Episode::factory()->create(['show_id' => $show->id, 'season_number' => 1, 'episode_number' => 1]);
    WatchedEpisode::factory()->create(['user_id' => $user->id, 'episode_id' => $watched->id]);

    Livewire::actingAs($user)->test('pages::show', ['show' => $show])
        ->assertSee('1/1')
        ->call('toggleSeason', 1)
        ->assertSeeHtml('bg-green-600');
});

it('toggles a season open and closed', function () {
    $user = User::factory()->create();
    $show = Show::factory()->create();
    Episode::factory()->create(['show_id' => $show->id, 'season_number' => 1, 'episode_number' => 1, 'name' => 'Primo']);
    Episode::factory()->create(['show_id' => $show->id, 'season_number' => 2, 'episode_number' => 1, 'name' => 'Secondo']);

    // La prima stagione è aperta di default, la seconda chiusa.
    Livewire::actingAs($user)->test('pages::show', ['show' => $show])
        ->assertSee('Primo')
        ->assertDontSee('Secondo')
        ->call('toggleSeason', 2)
        ->assertSee('Secondo')
        ->call('toggleSeason', 2)
        ->assertDontSee('Secondo');
});

it('updates the watched tick and count in the accordion after toggling', function () {
    $user = User::factory()->create();
    $show = Show::factory()->create();
    $ep = Episode::factory()->create(['show_id' => $show->id, 'season_number' => 1, 'episode_number' => 1]);

    Livewire::actingAs($user)->test('pages::show', ['show' => $show])
        ->assertDontSeeHtml('bg-green-600')
        ->call('toggle', $ep->id)
        ->assertSee('1/1')
        ->assertSeeHtml('bg-green-600');
});

it('lists the specials season after the regular ones', function () {
    $user = User::factory()->create();
    $show = showWithEpisodes([[0, 1], [1, 1], [2, 1]]);

    Livewire::actingAs($user)->test('pages::show', ['show' => $show])
        ->assertSeeInOrder(['Stagione 1', 'Stagione 2', 'Speciali']);
});

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

it('links back to the show and rates the episode', function () {
    $user = User::factory()->create();
    $show = Show::factory()->create(['name' => 'House']);
    $episode = Episode::factory()->create(['show_id' => $show->id, 'season_number' => 1, 'episode_number' => 2, 'name' => 'Paternity']);

    Livewire::actingAs($user)->test('pages::episode', ['episode' => $episode])
        ->assertSee('House')
        ->assertSeeHtml(route('shows.show', $show))
        ->call('rate', 4);

    $watch = WatchedEpisode::where(['user_id' => $user->id, 'episode_id' => $episode->id])->first();
    expect($watch)->not->toBeNull()
        ->and($watch->rating)->toBe(4)
        ->and($watch->watched_at)->not->toBeNull();
});

it('clears the episode rating when rating zero', function () {
    $user = User::factory()->create();
    $episode = Episode::factory()->create();
    WatchedEpisode::factory()->create(['user_id' => $user->id, 'episode_id' => $episode->id, 'rating' => 5]);

    Livewire::actingAs($user)->test('pages::episode', ['episode' => $episode])
        ->call('rate', 0);

    expect(WatchedEpisode::where(['user_id' => $user->id, 'episode_id' => $episode->id])->first()->rating)->toBeNull();
});

it('shows where to watch the episode', function () {
    config(['services.tmdb.token' => 'fake-token']);
    Http::fake([
        '*/watch/providers*' => Http::response(['results' => ['IT' => [
            'link' => 'https://justwatch/it',
            'flatrate' => [['provider_name' => 'Netflix', 'logo_path' => '/n.jpg']],
        ]]]),
    ]);

    $user = User::factory()->create();
    $show = Show::factory()->create(['tmdb_id' => 42]);
    $episode = Episode::factory()->create(['show_id' => $show->id]);

    Livewire::actingAs($user)->test('pages::episode', ['episode' => $episode])
        ->assertSee('Dove vederlo')
        ->assertSee('Netflix');
});
