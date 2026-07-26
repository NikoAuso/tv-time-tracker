<?php

use App\Models\Episode;
use App\Models\Show;
use App\Models\User;
use App\Models\UserShow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('lists only future episodes of followed shows in the calendar view', function () {
    $user = User::factory()->create();
    $show = Show::factory()->create(['name' => 'Severance']);
    UserShow::factory()->create(['user_id' => $user->id, 'show_id' => $show->id, 'status' => 'following']);

    Episode::factory()->create(['show_id' => $show->id, 'season_number' => 2, 'episode_number' => 1,
        'name' => 'Nuovo inizio', 'air_date' => now()->addWeek()]);
    // già uscito: il calendario mostra solo le uscite future
    Episode::factory()->create(['show_id' => $show->id, 'season_number' => 1, 'episode_number' => 1,
        'name' => 'Vecchio episodio', 'air_date' => now()->subMonth()]);

    Livewire::actingAs($user)->test('pages::dashboard')
        ->set('view', 'calendar')
        ->assertSee('Severance')
        ->assertSee('Nuovo inizio')
        ->assertDontSee('Vecchio episodio');
});

it('ignores upcoming episodes of shows that are not followed', function () {
    $user = User::factory()->create();
    $followed = Show::factory()->create(['name' => 'Seguita']);
    $other = Show::factory()->create(['name' => 'Non seguita']);
    UserShow::factory()->create(['user_id' => $user->id, 'show_id' => $followed->id, 'status' => 'following']);

    Episode::factory()->create(['show_id' => $followed->id, 'season_number' => 1, 'episode_number' => 1, 'air_date' => now()->addDays(3)]);
    Episode::factory()->create(['show_id' => $other->id, 'season_number' => 1, 'episode_number' => 1, 'air_date' => now()->addDays(3)]);

    Livewire::actingAs($user)->test('pages::dashboard')
        ->set('view', 'calendar')
        ->assertSee('Seguita')
        ->assertDontSee('Non seguita');
});

it('shows an empty state in the calendar when there are no upcoming episodes', function () {
    $user = User::factory()->create();
    $show = Show::factory()->create();
    UserShow::factory()->create(['user_id' => $user->id, 'show_id' => $show->id, 'status' => 'following']);
    Episode::factory()->create(['show_id' => $show->id, 'season_number' => 1, 'episode_number' => 1, 'air_date' => now()->subMonth()]);

    Livewire::actingAs($user)->test('pages::dashboard')
        ->set('view', 'calendar')
        ->assertSee('Nessuna uscita in programma');
});
