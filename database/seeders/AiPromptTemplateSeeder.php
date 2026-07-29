<?php

namespace Database\Seeders;

use App\Models\AiPromptTemplate;
use Illuminate\Database\Seeder;

class AiPromptTemplateSeeder extends Seeder
{
    /**
     * Loads prompts/*.txt verbatim as the template body - per
     * docs/07-Prompt-Engineering.md §5, those files are "the literal seed
     * value for ai_prompt_templates.template ... so Admins can edit them
     * post-launch through /admin/ai/prompt-templates", not something to
     * paraphrase here.
     */
    public function run(): void
    {
        $analyzeSchema = [
            'type' => 'object',
            'required' => ['summary', 'explanation', 'key_factors'],
            'properties' => [
                'summary' => ['type' => 'string'],
                'explanation' => ['type' => 'string'],
                'key_factors' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
        ];

        $templates = [
            [
                'key' => 'onboarding_analysis',
                'file' => 'onboarding.txt',
                'purpose' => 'Baseline analysis shown to a member right after completing onboarding.',
                'variables' => ['user_profile', 'rule_engine_output', 'kb_context', 'program_goal'],
                'response_schema' => $analyzeSchema,
            ],
            [
                'key' => 'meal_plan',
                'file' => 'meal-plan.txt',
                'purpose' => "A full day's meal plan (generatePlan meal portion / mealSuggestion).",
                'variables' => ['user_profile', 'rule_engine_output', 'kb_context', 'ai_memory_context', 'plan_date'],
                'response_schema' => [
                    'type' => 'object',
                    'required' => ['summary', 'meal_plan', 'motivation'],
                    'properties' => [
                        'summary' => ['type' => 'string'],
                        'meal_plan' => ['type' => 'array', 'items' => [
                            'type' => 'object',
                            'required' => ['meal_type', 'items', 'total_calories'],
                            'properties' => [
                                'meal_type' => ['type' => 'string'],
                                'items' => ['type' => 'array', 'items' => [
                                    'type' => 'object',
                                    'required' => ['name', 'portion', 'calories'],
                                    'properties' => [
                                        'name' => ['type' => 'string'],
                                        'portion' => ['type' => 'number'],
                                        'calories' => ['type' => 'number'],
                                    ],
                                ]],
                                'total_calories' => ['type' => 'number'],
                            ],
                        ]],
                        'motivation' => ['type' => 'string'],
                    ],
                ],
            ],
            [
                'key' => 'workout_plan',
                'file' => 'workout.txt',
                'purpose' => "A single day's workout plan (generatePlan workout portion / workoutSuggestion).",
                'variables' => ['user_profile', 'rule_engine_output', 'kb_context', 'ai_memory_context', 'plan_date'],
                'response_schema' => [
                    'type' => 'object',
                    'required' => ['summary', 'workout_plan', 'motivation'],
                    'properties' => [
                        'summary' => ['type' => 'string'],
                        'workout_plan' => ['type' => 'array', 'items' => [
                            'type' => 'object',
                            'required' => ['type', 'exercises', 'duration_minutes', 'intensity'],
                            'properties' => [
                                'type' => ['type' => 'string'],
                                'exercises' => ['type' => 'array', 'items' => [
                                    'type' => 'object',
                                    'required' => ['name', 'sets', 'reps'],
                                    'properties' => [
                                        'name' => ['type' => 'string'],
                                        'sets' => ['type' => 'integer'],
                                        'reps' => ['type' => 'integer'],
                                    ],
                                ]],
                                'duration_minutes' => ['type' => 'integer'],
                                'intensity' => ['type' => 'string'],
                            ],
                        ]],
                        'motivation' => ['type' => 'string'],
                    ],
                ],
            ],
            [
                'key' => 'weekly_review',
                'file' => 'weekly-review.txt',
                'purpose' => "Weekly progress summary + next week's adjustments.",
                'variables' => ['user_profile', 'rule_engine_output', 'program_goal', 'progress_snapshot', 'ai_memory_context'],
                'response_schema' => [
                    'type' => 'object',
                    'required' => ['summary', 'trend', 'adjustments', 'motivation'],
                    'properties' => [
                        'summary' => ['type' => 'string'],
                        'trend' => ['type' => 'string'],
                        'adjustments' => ['type' => 'array', 'items' => [
                            'type' => 'object',
                            'required' => ['type', 'detail', 'auto_applicable'],
                            'properties' => [
                                'type' => ['type' => 'string'],
                                'detail' => ['type' => 'string'],
                                'auto_applicable' => ['type' => 'boolean'],
                            ],
                        ]],
                        'motivation' => ['type' => 'string'],
                    ],
                ],
            ],
            [
                'key' => 'daily_chat',
                'file' => 'daily-chat.txt',
                'purpose' => 'Member <-> AI assistant conversation turn.',
                'variables' => ['user_profile', 'progress_snapshot', 'kb_context', 'ai_memory_context', 'conversation_history', 'member_message'],
                'response_schema' => [
                    'type' => 'object',
                    'required' => ['reply'],
                    'properties' => [
                        'reply' => ['type' => 'string'],
                        'suggested_actions' => ['type' => 'array', 'items' => [
                            'type' => 'object',
                            'required' => ['type', 'label'],
                            'properties' => [
                                'type' => ['type' => 'string'],
                                'label' => ['type' => 'string'],
                                'payload' => ['type' => 'object'],
                            ],
                        ]],
                    ],
                ],
            ],
            [
                'key' => 'coach_review',
                'file' => 'coach-review.txt',
                'purpose' => "Coach-facing analysis of a member's AI-flagged concern and pending recommendations.",
                'variables' => ['user_profile', 'rule_engine_output', 'progress_snapshot', 'ai_memory_context', 'pending_recommendations'],
                'response_schema' => [
                    'type' => 'object',
                    'required' => ['summary', 'recommendation_notes', 'manual_checks'],
                    'properties' => [
                        'summary' => ['type' => 'string'],
                        'recommendation_notes' => ['type' => 'array', 'items' => [
                            'type' => 'object',
                            'required' => ['recommendation_index', 'rationale'],
                            'properties' => [
                                'recommendation_index' => ['type' => 'integer'],
                                'rationale' => ['type' => 'string'],
                            ],
                        ]],
                        'manual_checks' => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                ],
            ],
        ];

        foreach ($templates as $template) {
            $file = $template['file'];
            unset($template['file']);

            AiPromptTemplate::updateOrCreate(
                ['key' => $template['key']],
                [...$template, 'template' => file_get_contents(base_path("prompts/{$file}"))],
            );
        }
    }
}
