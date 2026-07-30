<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkoutPlanItem extends Model
{
    protected $fillable = [
        'workout_plan_id',
        'kb_exercise_id',
        'custom_name',
        'sets',
        'reps',
        'duration_seconds',
        'order',
    ];

    public function workoutPlan(): BelongsTo
    {
        return $this->belongsTo(WorkoutPlan::class);
    }

    public function kbExercise(): BelongsTo
    {
        return $this->belongsTo(KbExercise::class, 'kb_exercise_id');
    }
}
