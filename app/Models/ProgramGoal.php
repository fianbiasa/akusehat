<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramGoal extends Model
{
    protected $fillable = [
        'user_program_id',
        'goal_type',
        'target_weight_kg',
        'target_waist_cm',
        'target_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'target_date' => 'date:Y-m-d',
        ];
    }

    public function userProgram(): BelongsTo
    {
        return $this->belongsTo(UserProgram::class);
    }
}
