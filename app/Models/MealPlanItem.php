<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealPlanItem extends Model
{
    protected $fillable = [
        'meal_plan_id',
        'kb_food_id',
        'custom_name',
        'portion',
        'calories',
    ];

    public function mealPlan(): BelongsTo
    {
        return $this->belongsTo(MealPlan::class);
    }

    public function kbFood(): BelongsTo
    {
        return $this->belongsTo(KbFood::class, 'kb_food_id');
    }
}
