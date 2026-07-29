<?php

namespace Tests\Feature\Profile;

use App\Models\KbDisease;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthProfileTest extends TestCase
{
    use RefreshDatabase;

    private function onboardedMember(): User
    {
        return User::factory()->create(['onboarding_completed_at' => now()]);
    }

    public function test_a_member_who_has_not_onboarded_cannot_reach_profile_routes()
    {
        $user = User::factory()->create(['onboarding_completed_at' => null]);

        $this->actingAs($user)->get('/profile/health')->assertRedirect(route('onboarding.index'));
    }

    public function test_the_health_profile_page_renders()
    {
        $this->actingAs($this->onboardedMember())->get('/profile/health')->assertOk();
    }

    public function test_updating_the_health_profile_recalculates_bmi()
    {
        $user = $this->onboardedMember();

        $this->actingAs($user)->patch('/profile/health', [
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
            'height_cm' => 175,
            'initial_weight_kg' => 80,
        ])->assertSessionHasNoErrors();

        $profile = $user->healthProfile()->first();
        $this->assertNotNull($profile->bmi);
        $this->assertEqualsWithDelta(80 / (1.75 ** 2), (float) $profile->bmi, 0.01);
    }

    public function test_updating_the_health_profile_rejects_an_invalid_gender()
    {
        $user = $this->onboardedMember();

        $this->actingAs($user)->patch('/profile/health', ['gender' => 'other'])->assertSessionHasErrors('gender');
    }

    public function test_updating_lifestyle_profile_recalculates_tdee_via_the_observer()
    {
        $user = $this->onboardedMember();
        $user->healthProfile()->create(['gender' => 'male', 'date_of_birth' => '1990-01-01', 'height_cm' => 175, 'initial_weight_kg' => 80]);
        $user->lifestyleProfile()->create(['activity_level' => 'sedentary']);

        $sedentaryTdee = (float) $user->healthProfile->fresh()->tdee;

        $this->actingAs($user)->patch('/profile/lifestyle', ['activity_level' => 'heavy'])->assertSessionHasNoErrors();

        $heavyTdee = (float) $user->healthProfile->fresh()->tdee;
        $this->assertGreaterThan($sedentaryTdee, $heavyTdee);
    }

    public function test_a_member_can_add_and_remove_a_disease()
    {
        $user = $this->onboardedMember();
        $disease = KbDisease::first();

        $this->actingAs($user)->post('/profile/diseases', ['kb_disease_id' => $disease->id])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('user_diseases', ['user_id' => $user->id, 'kb_disease_id' => $disease->id]);

        $userDisease = $user->diseases()->first();
        $this->actingAs($user)->delete("/profile/diseases/{$userDisease->id}")->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('user_diseases', ['id' => $userDisease->id]);
    }

    public function test_a_member_cannot_delete_another_members_disease()
    {
        $owner = $this->onboardedMember();
        $intruder = $this->onboardedMember();
        $disease = $owner->diseases()->create(['kb_disease_id' => KbDisease::first()->id]);

        $this->actingAs($intruder)->delete("/profile/diseases/{$disease->id}")->assertForbidden();
    }

    public function test_a_member_can_add_an_allergy()
    {
        $user = $this->onboardedMember();

        $this->actingAs($user)->post('/profile/allergies', ['allergen' => 'Udang', 'severity' => 'severe'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('user_allergies', ['user_id' => $user->id, 'allergen' => 'Udang', 'severity' => 'severe']);
    }

    public function test_a_member_can_manage_medications()
    {
        $user = $this->onboardedMember();

        $this->actingAs($user)->post('/profile/medications', ['name' => 'Metformin', 'dosage' => '500mg'])->assertSessionHasNoErrors();
        $medication = $user->medications()->first();

        $this->actingAs($user)->patch("/profile/medications/{$medication->id}", ['name' => 'Metformin', 'dosage' => '850mg'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('user_medications', ['id' => $medication->id, 'dosage' => '850mg']);

        $this->actingAs($user)->delete("/profile/medications/{$medication->id}")->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('user_medications', ['id' => $medication->id]);
    }

    public function test_logging_a_measurement_for_an_existing_date_updates_it_instead_of_duplicating()
    {
        $user = $this->onboardedMember();

        $this->actingAs($user)->post('/profile/measurements', ['measured_at' => '2026-01-01', 'weight_kg' => 80])->assertSessionHasNoErrors();
        $this->actingAs($user)->post('/profile/measurements', ['measured_at' => '2026-01-01', 'weight_kg' => 79])->assertSessionHasNoErrors();

        $this->assertSame(1, $user->bodyMeasurements()->count());
        $this->assertEqualsWithDelta(79.0, (float) $user->bodyMeasurements()->first()->weight_kg, 0.01);
    }
}
