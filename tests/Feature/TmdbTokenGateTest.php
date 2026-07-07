<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects to the token screen when the user has no TMDB token', function () {
    $user = User::factory()->withoutTmdbToken()->create();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertRedirect(route('token.edit'));
});

it('lets the token screen through without a token', function () {
    $user = User::factory()->withoutTmdbToken()->create();

    $this->actingAs($user)->get(route('token.edit'))->assertOk();
});

it('allows the app and injects the user token when present', function () {
    $user = User::factory()->create(['tmdb_token' => 'my-real-token']);

    $this->actingAs($user)->get(route('dashboard'))->assertOk();

    expect(config('services.tmdb.token'))->toBe('my-real-token');
});
