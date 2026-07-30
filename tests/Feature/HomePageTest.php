<?php

namespace Tests\Feature;

use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_homepage_renders_the_real_landing_page_with_active_plans()
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('plans', Plan::where('is_active', true)->count()));
    }

    public function test_inactive_plans_are_not_shown_on_the_homepage()
    {
        Plan::where('slug', 'gratis')->update(['is_active' => false]);

        $response = $this->get('/');

        $response->assertInertia(fn ($page) => $page->has('plans', Plan::where('is_active', true)->count()));
    }
}
