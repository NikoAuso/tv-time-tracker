<?php

use App\Models\Movie;
use App\Models\Show;
use App\Models\User;
use App\Models\UserMovie;
use App\Models\UserShow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('toggles a show as favorite', function () {
    $user = User::factory()->create();
    $show = Show::factory()->create();
    $entry = UserShow::factory()->create(['user_id' => $user->id, 'show_id' => $show->id, 'is_favorite' => false]);

    Livewire::actingAs($user)->test('pages::show', ['show' => $show])->call('toggleFavorite');
    expect($entry->fresh()->is_favorite)->toBeTrue();

    Livewire::actingAs($user)->test('pages::show', ['show' => $show])->call('toggleFavorite');
    expect($entry->fresh()->is_favorite)->toBeFalse();
});

it('toggles a movie as favorite', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();
    $entry = UserMovie::factory()->create(['user_id' => $user->id, 'movie_id' => $movie->id, 'is_favorite' => false]);

    Livewire::actingAs($user)->test('pages::movie', ['movie' => $movie])->call('toggleFavorite');
    expect($entry->fresh()->is_favorite)->toBeTrue();
});

it('lists favorite series and movies in the profile', function () {
    $user = User::factory()->create();

    $favShow = Show::factory()->create(['name' => 'Serie Cuore', 'poster_path' => '/s.jpg']);
    UserShow::factory()->create(['user_id' => $user->id, 'show_id' => $favShow->id, 'is_favorite' => true]);

    $favMovie = Movie::factory()->create(['title' => 'Film Cuore', 'poster_path' => '/m.jpg']);
    UserMovie::factory()->create(['user_id' => $user->id, 'movie_id' => $favMovie->id, 'is_favorite' => true]);

    Livewire::actingAs($user)->test('pages::settings.profile')
        ->assertSee(route('shows.show', $favShow), false)
        ->assertSee(route('movies.show', $favMovie), false);
});
