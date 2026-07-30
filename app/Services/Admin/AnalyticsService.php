<?php

namespace App\Services\Admin;

use App\Models\AiRequestLog;
use App\Models\HealthScore;
use App\Models\User;
use App\Models\UserProgram;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    public function activeUsers(): int
    {
        return User::query()
            ->where('status', 'active')
            ->where('last_login_at', '>=', now()->subDays(30))
            ->count();
    }

    public function programCompletionPercent(): float
    {
        $total = UserProgram::query()->count();

        if ($total === 0) {
            return 0.0;
        }

        $completed = UserProgram::query()->where('status', 'completed')->count();

        return round($completed / $total * 100, 1);
    }

    /**
     * Average of each user's most recent health score. `MAX(id)` per user
     * reliably picks the latest row since ids are monotonic and a user's
     * score for a given day is upserted in place (see HealthScoreService).
     */
    public function averageHealthScore(): ?float
    {
        $average = HealthScore::query()
            ->whereIn('id', function ($query) {
                $query->selectRaw('MAX(id)')->from('health_scores')->groupBy('user_id');
            })
            ->avg('score');

        return $average !== null ? round((float) $average, 1) : null;
    }

    public function aiCost30d(): float
    {
        return (float) AiRequestLog::query()
            ->where('created_at', '>=', now()->subDays(30))
            ->sum('estimated_cost');
    }

    /**
     * @return array<int, array{provider: string, cost: float, percent: float}>
     */
    public function aiCostByProvider30d(): array
    {
        $rows = AiRequestLog::query()
            ->join('ai_providers', 'ai_providers.id', '=', 'ai_request_logs.provider_id')
            ->where('ai_request_logs.created_at', '>=', now()->subDays(30))
            ->groupBy('ai_providers.id', 'ai_providers.name')
            ->orderByDesc(DB::raw('SUM(ai_request_logs.estimated_cost)'))
            ->get([
                'ai_providers.name as provider',
                DB::raw('SUM(ai_request_logs.estimated_cost) as cost'),
            ]);

        $total = (float) $rows->sum('cost');

        return $rows->map(fn ($row) => [
            'provider' => $row->provider,
            'cost' => round((float) $row->cost, 2),
            'percent' => $total > 0 ? round((float) $row->cost / $total * 100, 1) : 0.0,
        ])->values()->all();
    }

    public function summary(): array
    {
        return [
            'active_users' => $this->activeUsers(),
            'program_completion_percent' => $this->programCompletionPercent(),
            'avg_health_score' => $this->averageHealthScore(),
            'ai_cost_30d' => round($this->aiCost30d(), 2),
            'ai_cost_by_provider' => $this->aiCostByProvider30d(),
        ];
    }
}
