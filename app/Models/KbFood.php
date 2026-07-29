<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KbFood extends Model
{
    // Eloquent's pluralizer treats "Food" as uncountable and guesses
    // "kb_food" - the migration (correctly) uses "kb_foods".
    protected $table = 'kb_foods';

    protected $fillable = [
        'name',
        'name_local',
        'category',
        'serving_unit',
        'serving_size',
        'calories',
        'protein_g',
        'carbs_g',
        'fat_g',
        'fiber_g',
        'sodium_mg',
        'glycemic_index',
        'tags',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
        ];
    }
}
