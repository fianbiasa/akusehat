<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkoutPlan extends Model
{
    protected $fillable = [
        'user_program_id',
        'plan_date',
        'workout_type',
        'duration_minutes',
        'intensity',
        'is_completed',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'plan_date' => 'date:Y-m-d',
            'is_completed' => 'boolean',
        ];
    }

    public function userProgram(): BelongsTo
    {
        return $this->belongsTo(UserProgram::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(WorkoutPlanItem::class)->orderBy('order');
    }
}
