<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_dashboard_is_accessible(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('dashboard'))->assertOk();
    }

    public function test_a_pin_locks_the_app_until_unlocked(): void
    {
        $user = User::factory()->create(['pin' => '1234']);

        $this->actingAs($user);

        $this->get(route('dashboard'))->assertRedirect(route('unlock'));
    }

    public function test_unlock_page_is_reachable_while_locked(): void
    {
        $this->actingAs(User::factory()->create(['pin' => '1234']));

        $this->get(route('unlock'))->assertOk();
    }
}
