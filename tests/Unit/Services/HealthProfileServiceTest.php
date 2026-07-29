<?php

namespace Tests\Unit\Services;

use App\Models\Role;
use App\Models\User;
use App\Services\HealthProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class HealthProfileServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Reference values from docs/07-Prompt-Engineering.md §4's worked
     * example: age 39, height 167cm, weight 77.5kg.
     */
    public function test_bmi_bmr_tdee_match_the_prompt_engineering_worked_example()
    {
        $this->travelTo(Carbon::create(2026, 1, 1));

        $user = User::factory()->create(['role_id' => Role::where('name', 'member')->value('id')]);
        $user->healthProfile()->create([
            'gender' => 'male',
            'date_of_birth' => Carbon::create(2026, 1, 1)->subYears(39),
            'height_cm' => 167,
            'initial_weight_kg' => 77.5,
        ]);
        $user->lifestyleProfile()->create(['activity_level' => 'light']);

        $profile = (new HealthProfileService)->recalculate($user->fresh());

        $this->assertEqualsWithDelta(27.79, (float) $profile->bmi, 0.01);
        $this->assertEqualsWithDelta(1628.75, (float) $profile->bmr, 0.01);
        $this->assertEqualsWithDelta(2239.53, (float) $profile->tdee, 0.01);
    }

    public function test_female_bmr_uses_the_mifflin_st_jeor_female_offset()
    {
        $this->travelTo(Carbon::create(2026, 1, 1));

        $user = User::factory()->create(['role_id' => Role::where('name', 'member')->value('id')]);
        $user->healthProfile()->create([
            'gender' => 'female',
            'date_of_birth' => Carbon::create(2026, 1, 1)->subYears(30),
            'height_cm' => 160,
            'initial_weight_kg' => 60,
        ]);
        $user->lifestyleProfile()->create(['activity_level' => 'moderate']);

        $profile = (new HealthProfileService)->recalculate($user->fresh());

        $this->assertEqualsWithDelta(23.44, (float) $profile->bmi, 0.01);
        $this->assertEqualsWithDelta(1289.00, (float) $profile->bmr, 0.01);
        $this->assertEqualsWithDelta(1997.95, (float) $profile->tdee, 0.01);
    }

    public function test_activity_multipliers_produce_different_tdee_for_the_same_bmr()
    {
        $this->travelTo(Carbon::create(2026, 1, 1));

        $user = User::factory()->create(['role_id' => Role::where('name', 'member')->value('id')]);
        $user->healthProfile()->create([
            'gender' => 'male',
            'date_of_birth' => Carbon::create(2026, 1, 1)->subYears(39),
            'height_cm' => 167,
            'initial_weight_kg' => 77.5,
        ]);
        $service = new HealthProfileService;

        $user->lifestyleProfile()->create(['activity_level' => 'sedentary']);
        $sedentaryTdee = (float) $service->recalculate($user->fresh())->tdee;

        $user->lifestyleProfile->update(['activity_level' => 'heavy']);
        $heavyTdee = (float) $service->recalculate($user->fresh())->tdee;

        $this->assertEqualsWithDelta(1954.50, $sedentaryTdee, 0.01);
        $this->assertEqualsWithDelta(2809.59, $heavyTdee, 0.01);
        $this->assertGreaterThan($sedentaryTdee, $heavyTdee);
    }

    public function test_recalculate_uses_the_latest_body_measurement_over_initial_weight()
    {
        $this->travelTo(Carbon::create(2026, 1, 1));

        $user = User::factory()->create(['role_id' => Role::where('name', 'member')->value('id')]);
        $user->healthProfile()->create([
            'gender' => 'male',
            'date_of_birth' => Carbon::create(2026, 1, 1)->subYears(39),
            'height_cm' => 167,
            'initial_weight_kg' => 77.5,
        ]);
        $user->lifestyleProfile()->create(['activity_level' => 'sedentary']);

        // BodyMeasurementObserver recalculates automatically on create.
        $user->bodyMeasurements()->create(['measured_at' => '2026-01-15', 'weight_kg' => 70]);

        $profile = $user->healthProfile->fresh();

        // 70kg should pull BMI/BMR down relative to the 77.5kg baseline.
        $this->assertLessThan(27.79, (float) $profile->bmi);
    }

    public function test_recalculate_returns_early_when_the_profile_is_incomplete()
    {
        $user = User::factory()->create(['role_id' => Role::where('name', 'member')->value('id')]);
        $user->healthProfile()->create(['gender' => 'male']); // no height/date_of_birth

        $profile = (new HealthProfileService)->recalculate($user);

        $this->assertNull($profile->bmi);
    }
}
