<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use App\Models\UserList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('profile.edit'))->assertOk();
    }

    public function test_name_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test('pages::settings.profile')
            ->set('name', 'Nuovo Nome')
            ->call('saveName')
            ->assertHasNoErrors();

        $this->assertEquals('Nuovo Nome', $user->refresh()->name);
    }

    public function test_name_is_required(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test('pages::settings.profile')
            ->set('name', '')
            ->call('saveName')
            ->assertHasErrors('name');
    }

    public function test_a_list_can_be_created_from_the_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test('pages::settings.profile')
            ->set('newListName', 'Preferiti')
            ->call('createList')
            ->assertHasNoErrors();

        $this->assertTrue(
            UserList::where('user_id', $user->id)->where('name', 'Preferiti')->exists()
        );
    }
}
