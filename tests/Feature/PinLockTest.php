<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('unlocks with the correct pin', function () {
    $user = User::factory()->create(['pin' => '1234']);

    Livewire::actingAs($user)->test('pages::unlock')
        ->set('pin', '1234')
        ->call('unlock')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    expect(session('pin_unlocked'))->toBeTrue();
});

it('rejects a wrong pin', function () {
    $user = User::factory()->create(['pin' => '1234']);

    Livewire::actingAs($user)->test('pages::unlock')
        ->set('pin', '9999')
        ->call('unlock')
        ->assertHasErrors('pin');

    expect(session('pin_unlocked'))->not->toBeTrue();
});

it('sets a pin from settings', function () {
    $user = User::factory()->create(['pin' => null]);

    Livewire::actingAs($user)->test('pages::settings.pin')
        ->set('pin', '1234')
        ->set('pin_confirmation', '1234')
        ->call('updatePin')
        ->assertHasNoErrors();

    expect(Hash::check('1234', $user->fresh()->pin))->toBeTrue();
});

it('removes a pin from settings', function () {
    $user = User::factory()->create(['pin' => '1234']);

    Livewire::actingAs($user)->test('pages::settings.pin')
        ->call('removePin')
        ->assertHasNoErrors();

    expect($user->fresh()->pin)->toBeNull();
});
