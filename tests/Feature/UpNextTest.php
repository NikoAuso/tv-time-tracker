<?php

use App\Models\Episode;
use App\Models\Show;
use App\Models\User;
use App\Models\UserShow;
use App\Models\WatchedEpisode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function followedShow(User $user): Show
{
    $show = Show::factory()->create(['name' => 'House']);
    UserShow::factory()->create(['user_id' => $user->id, 'show_id' => $show->id, 'status' => 'following']);

    return $show;
}

it('shows the first unwatched episode as up next', function () {
    $user = User::factory()->create();
    $show = followedShow($user);
    $e1 = Episode::factory()->create(['show_id' => $show->id, 'season_number' => 1, 'episode_number' => 1, 'name' => 'Pilot']);
    Episode::factory()->create(['show_id' => $show->id, 'season_number' => 1, 'episode_number' => 2, 'name' => 'Paternity']);
    WatchedEpisode::factory()->create(['user_id' => $user->id, 'episode_id' => $e1->id]);

    Livewire::actingAs($user)->test('pages::dashboard')
        ->assertSee('House')
        ->assertSee('Paternity')
        ->assertDontSee('Pilot');
});

it('advances to done when marking the last aired episode watched', function () {
    $user = User::factory()->create();
    $show = followedShow($user);
    $e1 = Episode::factory()->create(['show_id' => $show->id, 'season_number' => 1, 'episode_number' => 1]);
    WatchedEpisode::factory()->create(['user_id' => $user->id, 'episode_id' => $e1->id]);
    $e2 = Episode::factory()->create(['show_id' => $show->id, 'season_number' => 1, 'episode_number' => 2, 'air_date' => now()->subDay()]);
    // Episodio futuro: non deve contare come "da guardare".
    Episode::factory()->create(['show_id' => $show->id, 'season_number' => 1, 'episode_number' => 3, 'air_date' => now()->addMonth()]);

    Livewire::actingAs($user)->test('pages::dashboard')
        ->call('markWatched', $e2->id)
        ->assertSee('Sei in pari');
});
