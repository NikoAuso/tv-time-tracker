<?php

use App\Models\Episode;
use App\Models\Show;
use App\Models\User;
use App\Models\UserShow;
use App\Models\WatchedEpisode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('shows the followed shows in the library', function () {
    $user = User::factory()->create();
    $show = Show::factory()->create(['name' => 'House']);
    UserShow::factory()->create(['user_id' => $user->id, 'show_id' => $show->id, 'status' => 'following']);

    $this->actingAs($user)->get(route('library'))
        ->assertOk()
        ->assertSee('House');
});

it('toggles an episode watched state', function () {
    $user = User::factory()->create();
    $show = Show::factory()->create();
    $episode = Episode::factory()->create(['show_id' => $show->id, 'season_number' => 1, 'episode_number' => 1]);
    WatchedEpisode::factory()->create(['user_id' => $user->id, 'episode_id' => $episode->id]);

    Livewire::actingAs($user)->test('pages::show', ['show' => $show])
        ->call('toggle', $episode->id)
        ->assertOk();
    expect(WatchedEpisode::count())->toBe(0);

    Livewire::actingAs($user)->test('pages::show', ['show' => $show])
        ->call('toggle', $episode->id);
    expect(WatchedEpisode::where('user_id', $user->id)->where('episode_id', $episode->id)->exists())->toBeTrue();
});

it('adds a watched episode manually', function () {
    $user = User::factory()->create();
    $show = Show::factory()->create();

    Livewire::actingAs($user)->test('pages::show', ['show' => $show])
        ->set('newSeason', 2)
        ->set('newEpisode', 5)
        ->call('addEpisode')
        ->assertHasNoErrors();

    $episode = Episode::where('show_id', $show->id)->where('season_number', 2)->where('episode_number', 5)->first();
    expect($episode)->not->toBeNull();
    expect(WatchedEpisode::where('user_id', $user->id)->where('episode_id', $episode->id)->exists())->toBeTrue();
});
