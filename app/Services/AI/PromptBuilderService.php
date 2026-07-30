<?php

namespace App\Services\AI;

use App\Models\AiPromptTemplate;
use App\Models\KbExercise;
use App\Models\KbFood;
use App\Models\ProgramGoal;
use App\Models\User;
use App\Services\RuleEngine\RuleEngineService;

/**
 * Assembles a final prompt string from an ai_prompt_templates row +
 * resolved variables, per docs/07-Prompt-Engineering.md §1. The fixed
 * JSON-output instruction block is *part of* every seeded template file
 * (see prompts/*.txt) rather than appended separately here - Admins can
 * still edit everything except that trailing block through the template
 * editor's own guardrails (Phase 5's Admin UI doesn't enforce that yet;
 * noted in docs/11-Development-Checklist.md).
 */
class PromptBuilderService
{
    public function __construct(private RuleEngineService $ruleEngineService) {}

    /**
     * @return array{prompt: string, response_schema: array, template_key: string, template_version: int}
     */
    public function build(string $templateKey, User $user, array $extra = []): array
    {
        $template = AiPromptTemplate::where('key', $templateKey)->where('is_active', true)->firstOrFail();

        $variableNames = array_unique([...$template->variables, 'user_locale', 'response_schema_json']);
        $placeholders = [];

        foreach ($variableNames as $name) {
            $value = $extra[$name] ?? $this->resolve($name, $user, $template);
            $placeholders['{{'.$name.'}}'] = is_string($value) ? $value : json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return [
            'prompt' => strtr($template->template, $placeholders),
            'response_schema' => $template->response_schema,
            'template_key' => $templateKey,
            'template_version' => $template->version,
        ];
    }

    private function resolve(string $name, User $user, AiPromptTemplate $template): mixed
    {
        return match ($name) {
            'user_locale' => $user->locale,
            'response_schema_json' => $template->response_schema,
            'user_profile' => $this->resolveUserProfile($user),
            'rule_engine_output' => $this->ruleEngineService->evaluate($user),
            'kb_context' => $this->resolveKbContext($user),
            'ai_memory_context' => $this->resolveAiMemoryContext($user),
            'progress_snapshot' => $this->resolveProgressSnapshot($user),
            'program_goal' => $this->resolveProgramGoal($user),
            'pending_recommendations' => $this->resolvePendingRecommendations($user),
            // conversation_history / member_message depend on features not
            // built yet (conversations/messages is Phase 8) - callers pass
            // these via $extra until then. plan_date is inherently
            // call-specific and always caller-supplied.
            default => 'Tidak ada data.',
        };
    }

    private function resolveUserProfile(User $user): array
    {
        $health = $user->healthProfile;
        $lifestyle = $user->lifestyleProfile;

        return [
            'name' => $user->name,
            'age' => $health?->date_of_birth?->age,
            'gender' => $health?->gender,
            'height_cm' => $health?->height_cm,
            'weight_kg' => $user->bodyMeasurements()->whereNotNull('weight_kg')->latest('measured_at')->value('weight_kg')
                ?? $health?->initial_weight_kg,
            'activity_level' => $lifestyle?->activity_level,
            'diseases' => $user->diseases()->with('disease:id,name')->get()->pluck('disease.name')->values(),
            'allergies' => $user->allergies()->pluck('allergen')->values(),
        ];
    }

    /**
     * Candidate foods (sorted so restriction-matching tags come first) and
     * exercises (hard-excluding anything contraindicated for the user's
     * diseases - workout.txt requires this to NEVER happen, not just
     * "prefer avoiding").
     */
    private function resolveKbContext(User $user): array
    {
        $restrictions = collect($this->ruleEngineService->evaluate($user)['restrictions']);
        $diseaseSlugs = $user->diseases()->with('disease:id,slug')->get()->pluck('disease.slug')->filter()->values();

        $foods = KbFood::query()
            ->limit(40)
            ->get(['name_local', 'category', 'calories', 'protein_g', 'carbs_g', 'fat_g', 'glycemic_index', 'tags'])
            ->sortByDesc(fn (KbFood $food) => $restrictions->intersect($food->tags ?? [])->count())
            ->values();

        $exercises = KbExercise::query()
            ->limit(40)
            ->get(['name', 'category', 'met_value', 'difficulty', 'contraindications'])
            ->reject(fn (KbExercise $exercise) => collect($exercise->contraindications ?? [])->intersect($diseaseSlugs)->isNotEmpty())
            ->values();

        return ['foods' => $foods, 'exercises' => $exercises];
    }

    private function resolveAiMemoryContext(User $user): array
    {
        return $user->aiMemories()
            ->orderByDesc('relevance_score')
            ->limit(5)
            ->get(['memory_type', 'summary'])
            ->toArray();
    }

    /**
     * weight_logs/checklist_items (Phase 7) don't exist yet - built from
     * body_measurements (Phase 3) in the meantime.
     */
    private function resolveProgressSnapshot(User $user): array
    {
        return [
            'recent_measurements' => $user->bodyMeasurements()->orderByDesc('measured_at')->limit(7)->get(['measured_at', 'weight_kg', 'waist_cm']),
        ];
    }

    /**
     * The active program's most recent goal, if any - callers generating a
     * plan for a specific UserProgram should still prefer passing
     * 'program_goal' via $extra explicitly (a user can run multiple
     * programs concurrently, per FR-PROG-01), this is only the fallback
     * for capabilities not scoped to one program (e.g. onboarding_analysis).
     */
    private function resolveProgramGoal(User $user): array|string
    {
        $goal = ProgramGoal::whereIn('user_program_id', $user->programs()->where('status', 'active')->pluck('id'))
            ->latest()
            ->first();

        if (! $goal) {
            return 'Tidak ada data.';
        }

        return [
            'goal_type' => $goal->goal_type,
            'target_weight_kg' => $goal->target_weight_kg,
            'target_waist_cm' => $goal->target_waist_cm,
            'target_date' => $goal->target_date,
            'notes' => $goal->notes,
        ];
    }

    private function resolvePendingRecommendations(User $user): array
    {
        return $user->aiRecommendations()
            ->where('status', 'pending')
            ->latest()
            ->limit(10)
            ->get(['type', 'content', 'rationale'])
            ->toArray();
    }
}
