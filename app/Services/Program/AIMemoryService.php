<?php

namespace App\Services\Program;

use App\Models\AiMemory;
use App\Models\User;
use App\Models\UserProgram;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Deterministic trend/pattern/milestone/concern detection from checklist
 * and body-measurement history (docs/04-Architecture.md §3/§6
 * ScanAIMemoryJob). These are heuristics, not ML - consistent with the
 * Rule-Engine-first design principle that ground truth never depends on
 * an AI call succeeding. One memory per (user, type) per day at most, so
 * re-running the scan (daily job, or the CheckInSubmitted listener) never
 * spams duplicate rows.
 */
class AIMemoryService
{
    public function scan(User $user): void
    {
        foreach ($user->activePrograms as $userProgram) {
            $this->detectChecklistMilestone($user, $userProgram);
            $this->detectChecklistConcern($user, $userProgram);
            $this->detectWorkoutPattern($user, $userProgram);
        }

        $this->detectWeightTrend($user);
    }

    private function detectChecklistMilestone(User $user, UserProgram $userProgram): void
    {
        $days = $this->dailyCompletionRates($userProgram, 7);

        if ($days->count() < 7 || $days->contains(fn (float $rate) => $rate < 1.0)) {
            return;
        }

        $this->rememberOncePerDay($user, 'milestone', 'Konsisten menyelesaikan seluruh checklist harian selama 7 hari terakhir.', [
            'user_program_id' => $userProgram->id,
            'streak_days' => 7,
        ]);
    }

    private function detectChecklistConcern(User $user, UserProgram $userProgram): void
    {
        $days = $this->dailyCompletionRates($userProgram, 3);

        if ($days->count() < 3 || $days->contains(fn (float $rate) => $rate > 0.0)) {
            return;
        }

        $this->rememberOncePerDay($user, 'concern', 'Tidak ada checklist yang diselesaikan dalam 3 hari terakhir.', [
            'user_program_id' => $userProgram->id,
            'zero_completion_days' => 3,
        ]);
    }

    private function detectWorkoutPattern(User $user, UserProgram $userProgram): void
    {
        $since = Carbon::today()->subDays(6);
        $items = $userProgram->checklistItems()->where('item_date', '>=', $since)->get();

        $mealPrefixes = ['Breakfast:', 'Lunch:', 'Dinner:', 'Snack:'];
        $workoutItems = $items->filter(fn ($i) => str_starts_with($i->label, 'Olahraga:'));
        $mealItems = $items->filter(fn ($i) => collect($mealPrefixes)->contains(fn ($prefix) => str_starts_with($i->label, $prefix)));

        if ($workoutItems->count() < 3 || $mealItems->isEmpty()) {
            return;
        }

        $workoutRate = $workoutItems->where('is_checked', true)->count() / $workoutItems->count();
        $mealRate = $mealItems->where('is_checked', true)->count() / $mealItems->count();

        if ($workoutRate >= 0.5 || $mealRate < 0.8) {
            return;
        }

        $this->rememberOncePerDay($user, 'pattern', 'Sering melewatkan sesi olahraga meski checklist makan tetap konsisten.', [
            'user_program_id' => $userProgram->id,
            'workout_completion_rate' => round($workoutRate, 2),
            'meal_completion_rate' => round($mealRate, 2),
        ]);
    }

    private function detectWeightTrend(User $user): void
    {
        $measurements = $user->weightLogs()
            ->where('logged_at', '>=', Carbon::today()->subDays(13))
            ->orderBy('logged_at')
            ->get(['weight_kg', 'logged_at']);

        if ($measurements->count() < 2) {
            return;
        }

        $delta = (float) $measurements->last()->weight_kg - (float) $measurements->first()->weight_kg;

        if (abs($delta) < 0.5) {
            return;
        }

        $direction = $delta < 0 ? 'menurun' : 'meningkat';

        $this->rememberOncePerDay($user, 'trend', "Berat badan {$direction} sebesar ".number_format(abs($delta), 1).' kg dalam 14 hari terakhir.', [
            'delta_kg' => round($delta, 1),
            'from' => $measurements->first()->weight_kg,
            'to' => $measurements->last()->weight_kg,
        ]);
    }

    /**
     * @return Collection<int, float>
     */
    private function dailyCompletionRates(UserProgram $userProgram, int $days): Collection
    {
        $since = Carbon::today()->subDays($days - 1);

        return $userProgram->checklistItems()
            ->where('item_date', '>=', $since)
            ->get()
            ->groupBy(fn ($item) => $item->item_date->toDateString())
            ->filter(fn ($items) => $items->isNotEmpty())
            ->map(fn ($items) => $items->where('is_checked', true)->count() / $items->count());
    }

    private function rememberOncePerDay(User $user, string $memoryType, string $summary, array $data): void
    {
        $alreadyRemembered = $user->aiMemories()
            ->where('memory_type', $memoryType)
            ->whereDate('created_at', Carbon::today())
            ->exists();

        if ($alreadyRemembered) {
            return;
        }

        AiMemory::create([
            'user_id' => $user->id,
            'user_program_id' => $data['user_program_id'] ?? null,
            'memory_type' => $memoryType,
            'summary' => $summary,
            'data' => $data,
        ]);
    }
}
