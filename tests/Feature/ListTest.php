<?php

use App\Models\Movie;
use App\Models\Show;
use App\Models\User;
use App\Models\UserList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('creates a list', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test('pages::lists')
        ->set('newName', 'Da recuperare')
        ->call('create')
        ->assertHasNoErrors();

    expect(UserList::where('user_id', $user->id)->where('name', 'Da recuperare')->exists())->toBeTrue();
});

it('requires a name to create a list', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test('pages::lists')
        ->set('newName', '')
        ->call('create')
        ->assertHasErrors('newName');
});

it('deletes a list', function () {
    $user = User::factory()->create();
    $list = UserList::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)->test('pages::lists')->call('delete', $list->id);

    expect(UserList::whereKey($list->id)->exists())->toBeFalse();
});

it('adds and removes a show via the show page', function () {
    $user = User::factory()->create();
    $show = Show::factory()->create();
    $list = UserList::factory()->create(['user_id' => $user->id]);

    $component = Livewire::actingAs($user)->test('pages::show', ['show' => $show]);

    $component->call('toggleList', $list->id);
    expect($list->shows()->whereKey($show->id)->exists())->toBeTrue();

    $component->call('toggleList', $list->id);
    expect($list->shows()->whereKey($show->id)->exists())->toBeFalse();
});

it('adds a movie to a list and lists it in the detail page', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create(['title' => 'Fury']);
    $list = UserList::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)->test('pages::movie', ['movie' => $movie])
        ->call('toggleList', $list->id);

    Livewire::actingAs($user)->test('pages::list', ['userList' => $list])
        ->assertSee('Fury');
});

it('forbids opening another user list', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $list = UserList::factory()->create(['user_id' => $owner->id]);

    Livewire::actingAs($intruder)->test('pages::list', ['userList' => $list])
        ->assertForbidden();
});

it('ignores toggling a list the user does not own', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $show = Show::factory()->create();
    $list = UserList::factory()->create(['user_id' => $owner->id]);

    Livewire::actingAs($intruder)->test('pages::show', ['show' => $show])
        ->call('toggleList', $list->id);

    expect($list->shows()->count())->toBe(0);
});

it('removes an item from the list detail page', function () {
    $user = User::factory()->create();
    $show = Show::factory()->create();
    $list = UserList::factory()->create(['user_id' => $user->id]);
    $list->shows()->attach($show->id);

    Livewire::actingAs($user)->test('pages::list', ['userList' => $list])
        ->call('remove', 'series', $show->id);

    expect($list->shows()->count())->toBe(0);
});
