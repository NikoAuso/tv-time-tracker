<?php

use App\Models\Episode;
use App\Models\Movie;
use App\Models\Show;
use App\Models\User;
use App\Models\UserMovie;
use App\Models\UserShow;
use App\Models\WatchedEpisode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('renders stats for the current user only', function () {
    $user = User::factory()->create();
    $house = Show::factory()->create(['name' => 'House']);
    UserShow::factory()->create(['user_id' => $user->id, 'show_id' => $house->id, 'status' => 'following']);

    Episode::factory()->count(3)
        ->sequence(fn ($seq) => ['episode_number' => $seq->index + 1])
        ->create(['show_id' => $house->id, 'season_number' => 1])
        ->each(fn (Episode $e) => WatchedEpisode::factory()->create([
            'user_id' => $user->id, 'episode_id' => $e->id, 'watched_at' => now(),
        ]));

    // Dati di un altro utente non devono comparire.
    $other = User::factory()->create();
    $secret = Show::factory()->create(['name' => 'SecretShow']);
    $ep = Episode::factory()->create(['show_id' => $secret->id]);
    WatchedEpisode::factory()->create(['user_id' => $other->id, 'episode_id' => $ep->id]);

    $this->actingAs($user)->get(route('stats'))
        ->assertOk()
        ->assertSee('Statistiche')
        ->assertSee('House')
        ->assertSee('Maratone')
        ->assertSee('Giornata record')
        ->assertDontSee('SecretShow');
});

it('shows movie stats on the film tab', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create(['title' => 'Fury', 'runtime' => 134, 'release_date' => '2014-10-15']);
    UserMovie::factory()->create(['user_id' => $user->id, 'movie_id' => $movie->id, 'status' => 'watched']);

    Livewire::actingAs($user)->test('pages::stats')
        ->set('tab', 'movies')
        ->assertSee('Durata media')
        ->assertSee('Maratone')
        ->assertSee('Film visti per decennio')
        ->assertSee('Anni 2010');
});
