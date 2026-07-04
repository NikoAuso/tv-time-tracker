<?php

use App\Models\Movie;
use App\Models\Show;
use App\Models\User;
use App\Models\UserMovie;
use App\Models\UserShow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('rates a followed show', function () {
    $user = User::factory()->create();
    $show = Show::factory()->create();
    UserShow::factory()->create(['user_id' => $user->id, 'show_id' => $show->id, 'status' => 'following']);

    Livewire::actingAs($user)->test('pages::show', ['show' => $show])
        ->call('rate', 4);

    expect(UserShow::where('user_id', $user->id)->where('show_id', $show->id)->value('rating'))->toBe(4);
});

it('clears a show rating with zero', function () {
    $user = User::factory()->create();
    $show = Show::factory()->create();
    UserShow::factory()->create(['user_id' => $user->id, 'show_id' => $show->id, 'rating' => 5]);

    Livewire::actingAs($user)->test('pages::show', ['show' => $show])
        ->call('rate', 0);

    expect(UserShow::where('user_id', $user->id)->where('show_id', $show->id)->value('rating'))->toBeNull();
});

it('caps a show rating at five', function () {
    $user = User::factory()->create();
    $show = Show::factory()->create();

    Livewire::actingAs($user)->test('pages::show', ['show' => $show])
        ->call('rate', 9);

    expect(UserShow::where('user_id', $user->id)->where('show_id', $show->id)->value('rating'))->toBe(5);
});

it('rates a movie', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();
    UserMovie::factory()->create(['user_id' => $user->id, 'movie_id' => $movie->id, 'status' => 'watched']);

    Livewire::actingAs($user)->test('pages::movie', ['movie' => $movie])
        ->call('rate', 3);

    expect(UserMovie::where('user_id', $user->id)->where('movie_id', $movie->id)->value('rating'))->toBe(3);
});
