<?php

namespace App\Http\Controllers\Progress;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Progress\Concerns\ResolvesTargetUser;
use App\Models\ChecklistItem;
use App\Services\RuleEngine\RuleEngineService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Loads a 90-day window once; the wireframe's Minggu/Bulan/90 Hari range
 * selector filters this already-loaded dataset client-side rather than
 * issuing a fresh request per range - the dataset is small enough that a
 * round-trip per range change would be pure overhead.
 *
 * Also serves the Coach's read-only variant via ?user_id= (wireframe/
 * progress.md: "Coach viewing this same page... sees identical charts
 * scoped to the selected member, minus any non-shared private photos").
 */
class ProgressPageController extends Controller
{
    use ResolvesTargetUser;

    public function __construct(private RuleEngineService $ruleEngineService) {}

    public function index(Request $request): Response
    {
        $user = $this->resolveTargetUser($request);
        $isOwner = $user->id === $request->user()->id;
        $windowStart = Carbon::today()->subDays(89);
        $weekStart = Carbon::today()->subDays(6);

        $waterLogs = $user->waterIntakeLogs()->where('logged_at', '>=', $weekStart)->get(['logged_at', 'amount_ml']);
        $waterDaily = $waterLogs->groupBy(fn ($log) => $log->logged_at->toDateString())->map(fn ($day) => $day->sum('amount_ml'));

        return Inertia::render('progress/index', [
            'readOnly' => ! $isOwner,
            'memberName' => $isOwner ? null : $user->name,
            'weightLogs' => $user->weightLogs()->where('logged_at', '>=', $windowStart)->orderBy('logged_at')->get(['logged_at', 'weight_kg']),
            'waistLogs' => $user->waistLogs()->where('logged_at', '>=', $windowStart)->orderBy('logged_at')->get(['logged_at', 'waist_cm']),
            'healthScores' => $user->healthScores()->where('scored_at', '>=', $windowStart)->orderBy('scored_at')->get(['scored_at', 'score', 'explanation']),
            'sleepAvg7d' => round((float) ($user->sleepLogs()->where('logged_at', '>=', $weekStart)->avg('sleep_hours') ?? 0), 1),
            'waterAvg7d' => (int) round($waterDaily->avg() ?? 0),
            'waterTargetMl' => $this->ruleEngineService->evaluate($user)['water_target_ml'],
            'photos' => $this->photos($user, $isOwner),
            'checklistConsistency' => $this->checklistConsistency($user),
        ]);
    }

    private function photos($user, bool $isOwner): array
    {
        return $user->progressPhotos()
            ->when(! $isOwner, fn ($q) => $q->where('is_private', false))
            ->orderByDesc('logged_at')
            ->get()
            ->map(fn ($photo) => [
                'id' => $photo->id,
                'logged_at' => $photo->logged_at->toDateString(),
                'angle' => $photo->angle,
                'is_private' => $photo->is_private,
                'url' => URL::temporarySignedRoute('progress.photos.show', now()->addMinutes(30), ['photo' => $photo->id]),
            ])->all();
    }

    private function checklistConsistency($user): array
    {
        $since = Carbon::today()->subDays(13);
        $programIds = $user->activePrograms()->pluck('id');

        $items = ChecklistItem::whereIn('user_program_id', $programIds)
            ->where('item_date', '>=', $since)
            ->get()
            ->groupBy(fn (ChecklistItem $item) => $item->item_date->toDateString());

        return collect(range(0, 13))->map(function (int $i) use ($items) {
            $date = Carbon::today()->subDays(13 - $i)->toDateString();
            $dayItems = $items->get($date, collect());

            return [
                'date' => $date,
                'completed' => $dayItems->isNotEmpty() && $dayItems->every(fn (ChecklistItem $item) => $item->is_checked),
            ];
        })->values()->all();
    }
}
