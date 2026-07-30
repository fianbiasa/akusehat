<?php

namespace App\Services;

use App\Models\User;

/**
 * PRD §13 "right-to-export" compliance flow. Scoped to data the user
 * can already see about themselves elsewhere in the app - deliberately
 * excludes: other users' data, coach_notes not marked
 * is_visible_to_member (the app's own coach->member visibility model
 * already decides what a member is allowed to see), any encrypted
 * secret (user_ai_settings.api_key_encrypted is never touched here,
 * same as it's never returned by any other API response), and
 * activity_logs (an internal audit trail about the platform's own
 * operations, not "the user's data" in the export sense).
 *
 * Whole models/collections are exported via toArray() rather than
 * manually rebuilding arrays, so each model's own date casts
 * (e.g. 'date:Y-m-d') are honored automatically - see the
 * manual-array-date-serialization gotcha documented elsewhere in this
 * app's history.
 */
class UserDataExportService
{
    public function export(User $user): array
    {
        return [
            'exported_at' => now()->toIso8601String(),
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'status' => $user->status,
                'timezone' => $user->timezone,
                'locale' => $user->locale,
                'created_at' => $user->created_at?->toIso8601String(),
            ],
            'health_profile' => $user->healthProfile?->toArray(),
            'lifestyle_profile' => $user->lifestyleProfile?->toArray(),
            'diseases' => $user->diseases()->with('disease:id,name')->get()->toArray(),
            'allergies' => $user->allergies()->get()->toArray(),
            'medications' => $user->medications()->get()->toArray(),
            'body_measurements' => $user->bodyMeasurements()->get()->toArray(),
            'programs' => $user->programs()->with(['program:id,name', 'goals'])->get()->toArray(),
            'weight_logs' => $user->weightLogs()->get()->toArray(),
            'waist_logs' => $user->waistLogs()->get()->toArray(),
            'body_fat_logs' => $user->bodyFatLogs()->get()->toArray(),
            'water_intake_logs' => $user->waterIntakeLogs()->get()->toArray(),
            'sleep_logs' => $user->sleepLogs()->get()->toArray(),
            'progress_photos' => $user->progressPhotos()->get(['id', 'logged_at', 'angle', 'is_private'])->toArray(),
            'health_scores' => $user->healthScores()->get()->toArray(),
            'achievements' => $user->achievements()->get(['achievements.id', 'achievements.name'])->toArray(),
            'ai_recommendations' => $user->aiRecommendations()->get(['id', 'type', 'content', 'rationale', 'status', 'created_at'])->toArray(),
            'ai_memories' => $user->aiMemories()->get(['id', 'memory_type', 'summary', 'data', 'created_at'])->toArray(),
            'ai_settings' => $user->aiSettings()->with(['provider:id,name', 'model:id,name'])->get(['id', 'provider_id', 'model_id', 'temperature', 'is_default'])->toArray(),
            'coach_notes_visible_to_me' => $user->coachNotesAbout()->where('is_visible_to_member', true)->with('coach:id,name')->get(['id', 'coach_id', 'note', 'created_at'])->toArray(),
            'coach_reviews_given' => $user->reviewsGiven()->with('coach:id,name')->get()->toArray(),
            'conversations' => $user->conversations()->with('messages')->get()->toArray(),
            'subscriptions' => $user->subscriptions()->with('plan:id,name')->get()->toArray(),
            'payments' => $user->subscriptions()->with('payments')->get()->pluck('payments')->flatten()->toArray(),
        ];
    }
}
