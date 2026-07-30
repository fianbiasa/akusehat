<?php

namespace Tests\Feature\Jobs;

use App\Jobs\EvaluateAchievementsJob;
use App\Models\Achievement;
use App\Models\Program;
use App\Models\User;
use App\Notifications\AchievementEarned;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EvaluateAchievementsJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_awards_an_achievement_the_user_now_qualifies_for_and_notifies_them()
    {
        Notification::fake();

        $user = User::factory()->create(['onboarding_completed_at' => now()]);
        $program = Program::where('slug', 'diet-90-hari')->firstOrFail();
        $user->programs()->create([
            'program_id' => $program->id,
            'status' => 'active',
            'start_date' => now()->subDays(31)->toDateString(),
            'created_by' => 'ai',
        ]);

        $achievement = Achievement::where('criteria->type', 'program_milestone_days')
            ->get()
            ->first(fn ($a) => $a->criteria['days'] === 30);

        app(EvaluateAchievementsJob::class)->handle(app(\App\Services\Achievements\AchievementCriteriaEvaluator::class));

        $this->assertDatabaseHas('user_achievements', ['user_id' => $user->id, 'achievement_id' => $achievement->id]);
        Notification::assertSentTo($user, AchievementEarned::class);
    }

    public function test_it_does_not_re_award_an_already_earned_achievement()
    {
        Notification::fake();

        $user = User::factory()->create(['onboarding_completed_at' => now()]);
        $program = Program::where('slug', 'diet-90-hari')->firstOrFail();
        $user->programs()->create([
            'program_id' => $program->id,
            'status' => 'active',
            'start_date' => now()->subDays(31)->toDateString(),
            'created_by' => 'ai',
        ]);

        $achievement = Achievement::where('criteria->type', 'program_milestone_days')
            ->get()
            ->first(fn ($a) => $a->criteria['days'] === 30);

        $user->userAchievements()->create(['achievement_id' => $achievement->id, 'earned_at' => now()->subDay()]);

        app(EvaluateAchievementsJob::class)->handle(app(\App\Services\Achievements\AchievementCriteriaEvaluator::class));

        $this->assertSame(1, $user->userAchievements()->where('achievement_id', $achievement->id)->count());
        Notification::assertNotSentTo($user, AchievementEarned::class);
    }

    public function test_a_user_who_does_not_qualify_for_anything_earns_nothing()
    {
        Notification::fake();

        $user = User::factory()->create(['onboarding_completed_at' => now()]);

        app(EvaluateAchievementsJob::class)->handle(app(\App\Services\Achievements\AchievementCriteriaEvaluator::class));

        $this->assertSame(0, $user->userAchievements()->count());
        Notification::assertNothingSent();
    }
}
