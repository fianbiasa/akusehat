<?php

namespace App\Jobs;

use App\Models\HealthScore;
use App\Models\User;
use App\Services\AI\AIGatewayService;
use App\Services\HealthScoreService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Scheduled daily (04-Architecture.md §6) for every onboarded user.
 * Computes the deterministic score/breakdown via HealthScoreService,
 * then asks the analyze() capability to narrate it (FR-TRK-03) - falling
 * back to a deterministic explanation naming the weakest component when
 * no AI provider is available, matching every other AI-touching job.
 */
class ComputeHealthScoreJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const COMPONENT_WEIGHTS = [
        'bmi' => 20, 'waist' => 10, 'sleep' => 15, 'water' => 10,
        'activity' => 15, 'weight_trend' => 15, 'checklist' => 10, 'disease_management' => 5,
    ];

    private const COMPONENT_LABELS = [
        'bmi' => 'indeks massa tubuh', 'waist' => 'lingkar pinggang', 'sleep' => 'tidur',
        'water' => 'asupan air', 'activity' => 'aktivitas olahraga', 'weight_trend' => 'tren berat badan',
        'checklist' => 'konsistensi checklist', 'disease_management' => 'manajemen kondisi kesehatan',
    ];

    public function handle(HealthScoreService $healthScoreService, AIGatewayService $aiGateway): void
    {
        User::whereNotNull('onboarding_completed_at')->chunkById(50, function ($users) use ($healthScoreService, $aiGateway) {
            foreach ($users as $user) {
                $this->scoreOne($user, $healthScoreService, $aiGateway);
            }
        });
    }

    private function scoreOne(User $user, HealthScoreService $healthScoreService, AIGatewayService $aiGateway): void
    {
        $breakdown = $healthScoreService->computeBreakdown($user);
        $score = round(array_sum($breakdown), 2);

        $result = $aiGateway->send($user, 'analyze', 'health_score_explain', ['health_score_breakdown' => $breakdown]);
        $explanation = ($result['ai_unavailable'] ?? false) ? $this->fallbackExplanation($breakdown) : ($result['explanation'] ?? null);

        $today = Carbon::today()->toDateString();
        $existing = HealthScore::where('user_id', $user->id)->where('scored_at', $today)->first();

        if ($existing) {
            $existing->update(['score' => $score, 'breakdown' => $breakdown, 'explanation' => $explanation]);
        } else {
            HealthScore::create([
                'user_id' => $user->id,
                'scored_at' => $today,
                'score' => $score,
                'breakdown' => $breakdown,
                'explanation' => $explanation,
                'created_at' => now(),
            ]);
        }
    }

    private function fallbackExplanation(array $breakdown): string
    {
        $weakest = collect($breakdown)->sortBy(fn ($score, $key) => $score / self::COMPONENT_WEIGHTS[$key])->keys()->first();

        return 'Skor kesehatanmu hari ini paling bisa ditingkatkan dari sisi '.self::COMPONENT_LABELS[$weakest].'. Tetap konsisten dan perlahan tingkatkan area ini.';
    }
}
