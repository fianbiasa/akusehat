<?php

namespace Tests\Feature\Progress;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgressPageControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_progress_page_renders_with_no_data()
    {
        $user = User::factory()->create(['onboarding_completed_at' => now()]);

        $this->actingAs($user)->get('/progress')->assertOk();
    }

    public function test_the_progress_page_renders_with_logged_data()
    {
        $user = User::factory()->create(['onboarding_completed_at' => now()]);
        $user->weightLogs()->create(['logged_at' => today(), 'weight_kg' => 75, 'created_at' => now()]);
        $user->healthScores()->create(['scored_at' => today(), 'score' => 80, 'breakdown' => [], 'created_at' => now()]);

        $response = $this->actingAs($user)->get('/progress');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('weightLogs', 1)->has('healthScores', 1)->has('checklistConsistency', 14));
    }

    public function test_photo_dates_in_the_page_props_are_plain_date_strings()
    {
        $user = User::factory()->create(['onboarding_completed_at' => now()]);
        $user->progressPhotos()->create(['logged_at' => today(), 'angle' => 'front', 'photo_path' => 'fake.jpg', 'is_private' => true, 'created_at' => now()]);

        $response = $this->actingAs($user)->get('/progress');

        $response->assertInertia(fn ($page) => $page->where('photos.0.logged_at', today()->toDateString()));
    }
}
