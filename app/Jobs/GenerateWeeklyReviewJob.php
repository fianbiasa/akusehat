<?php

namespace App\Jobs;

use App\Models\WeeklyPlan;
use App\Services\AI\AIGatewayService;
use App\Services\Program\RecommendationApplierService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Calls weeklyReview(), writes weekly_plans.ai_review (04-Architecture.md
 * §6), then routes any adjustments through RecommendationApplierService.
 * Dispatched per-program by the daily scheduler tick once that program's
 * current week has just ended (see routes/console.php) - "per program's
 * week boundary" varies by user (start_date-relative), not a fixed
 * calendar day shared by everyone.
 */
class GenerateWeeklyReviewJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public WeeklyPlan $weeklyPlan) {}

    public function handle(AIGatewayService $gateway, RecommendationApplierService $applier): void
    {
        $userProgram = $this->weeklyPlan->userProgram;
        $user = $userProgram->user;

        $result = $gateway->send($user, 'weeklyReview', 'weekly_review', [
            'plan_date' => $this->weeklyPlan->end_date->toDateString(),
        ]);

        $unavailable = (bool) ($result['ai_unavailable'] ?? false);
        $data = $unavailable ? $this->fallbackReview() : $result;

        $this->weeklyPlan->update([
            'ai_summary' => $data['summary'] ?? null,
            'ai_review' => $data,
            'generated_by' => $unavailable ? 'rule_engine' : 'ai',
        ]);

        $applier->applyAdjustments($userProgram, $data['adjustments'] ?? []);
    }

    private function fallbackReview(): array
    {
        return [
            'summary' => 'Ringkasan mingguan dasar dari Rule Engine (AI tidak tersedia).',
            'trend' => 'stagnant',
            'adjustments' => [],
            'motivation' => 'Terus lanjutkan progres kamu minggu ini!',
        ];
    }
}
