<?php

declare(strict_types=1);

use App\Models\Movie;
use App\Models\Show;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('resyncs the library from TMDB and populates providers', function () {
    config(['services.tmdb.token' => 'fake-token']);
    Http::fake([
        '*/tv/500/season/*' => Http::response(['episodes' => [['id' => 1, 'episode_number' => 1, 'name' => 'Ep']]]),
        '*/tv/500/watch/providers*' => Http::response(['results' => ['IT' => [
            'flatrate' => [['provider_name' => 'Netflix', 'logo_path' => '/n.jpg']],
        ]]]),
        '*/tv/500*' => Http::response(['id' => 500, 'name' => 'Synced', 'overview' => 'Trama', 'seasons' => [['season_number' => 1]]]),
        '*/movie/600/watch/providers*' => Http::response(['results' => ['IT' => [
            'flatrate' => [['provider_name' => 'Prime Video', 'logo_path' => '/p.jpg']],
        ]]]),
        '*/movie/600*' => Http::response(['id' => 600, 'title' => 'SyncedMovie', 'overview' => 'TramaFilm']),
        '*' => Http::response([]),
    ]);

    $user = User::factory()->create();
    $show = Show::factory()->create(['tmdb_id' => 500, 'overview' => null]);
    $movie = Movie::factory()->create(['tmdb_id' => 600, 'overview' => null]);

    Livewire::actingAs($user)->test('pages::settings.resync')->call('resync');

    expect($show->fresh()->overview)->toBe('Trama')
        ->and($show->fresh()->providers['flatrate'][0]['name'])->toBe('Netflix')
        ->and($movie->fresh()->providers['flatrate'][0]['name'])->toBe('Prime Video');
});

it('does nothing on resync without a TMDB token', function () {
    $user = User::factory()->withoutTmdbToken()->create();

    Livewire::actingAs($user)->test('pages::settings.resync')
        ->call('resync')
        ->assertHasNoErrors();
});
