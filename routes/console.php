<?php

use App\Jobs\ComputeHealthScoreJob;
use App\Jobs\DispatchRemindersJob;
use App\Jobs\EvaluateAchievementsJob;
use App\Jobs\GenerateDailyProgramPlansJob;
use App\Jobs\GenerateWeeklyReviewJob;
use App\Jobs\PruneAIMemoryRelevanceJob;
use App\Jobs\ScanAIMemoryJob;
use App\Jobs\SubscriptionRenewalCheckJob;
use App\Models\WeeklyPlan;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 04-Architecture.md §6 — Scheduled Jobs.
Schedule::job(new DispatchRemindersJob)->everyMinute();

Schedule::job(new GenerateDailyProgramPlansJob)->daily();

Schedule::job(new ScanAIMemoryJob)->daily();

Schedule::job(new ComputeHealthScoreJob)->daily();

Schedule::job(new EvaluateAchievementsJob)->daily();

Schedule::job(new SubscriptionRenewalCheckJob)->daily();

Schedule::job(new PruneAIMemoryRelevanceJob)->weekly();

// "Weekly (per program's week boundary)" is user-relative, not a shared
// calendar day - a daily tick finds whichever weekly_plans just ended
// yesterday and haven't been reviewed yet, and dispatches one job per row.
Schedule::call(function () {
    WeeklyPlan::whereNull('ai_review')
        ->whereDate('end_date', now()->subDay()->toDateString())
        ->each(fn (WeeklyPlan $weeklyPlan) => GenerateWeeklyReviewJob::dispatch($weeklyPlan));
})->daily()->name('dispatch-weekly-reviews');
