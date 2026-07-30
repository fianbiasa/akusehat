<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MealPlan extends Model
{
    protected $fillable = [
        'user_program_id',
        'plan_date',
        'meal_type',
        'total_calories',
        'total_protein_g',
        'total_carbs_g',
        'total_fat_g',
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
        return $this->hasMany(MealPlanItem::class);
    }
}
