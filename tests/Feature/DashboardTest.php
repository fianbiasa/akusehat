<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $this->actingAs($user = User::factory()->create(['onboarding_completed_at' => now()]));

        $this->get('/dashboard')->assertOk();
    }

    public function test_members_who_have_not_onboarded_are_redirected_to_the_wizard()
    {
        $this->actingAs(User::factory()->create(['onboarding_completed_at' => null]));

        $this->get('/dashboard')->assertRedirect(route('onboarding.index'));
    }
}
