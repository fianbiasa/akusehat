<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiRequestLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiRequestLogController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->only('purpose', 'status', 'provider_id');

        $logs = AiRequestLog::query()
            ->with(['provider:id,name', 'model:id,name', 'user:id,name'])
            ->when($filters['purpose'] ?? null, fn ($q, $purpose) => $q->where('purpose', $purpose))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['provider_id'] ?? null, fn ($q, $providerId) => $q->where('provider_id', $providerId))
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('admin/ai-request-logs/index', [
            'logs' => $logs,
            'purposes' => AiRequestLog::query()->distinct()->orderBy('purpose')->pluck('purpose'),
            'providers' => AiRequestLog::with('provider:id,name')->get()->pluck('provider')->filter()->unique('id')->values(),
            'stats' => $this->stats(),
            'filters' => $filters,
        ]);
    }

    private function stats(): array
    {
        $base = AiRequestLog::query();

        return [
            'total_requests' => (clone $base)->count(),
            'success_rate' => $this->successRate(clone $base),
            'total_cost' => (float) (clone $base)->sum('estimated_cost'),
            'avg_latency_ms' => (int) round((clone $base)->avg('latency_ms') ?? 0),
            'cost_by_purpose' => (clone $base)
                ->selectRaw('purpose, SUM(estimated_cost) as cost, COUNT(*) as requests')
                ->groupBy('purpose')
                ->orderByDesc('cost')
                ->get(),
        ];
    }

    private function successRate($query): float
    {
        $total = (clone $query)->count();

        if ($total === 0) {
            return 0;
        }

        $success = (clone $query)->where('status', 'success')->count();

        return round(($success / $total) * 100, 1);
    }
}
