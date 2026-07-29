<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KbExercise extends Model
{
    protected $fillable = [
        'name',
        'category',
        'target_muscle',
        'met_value',
        'difficulty',
        'equipment',
        'instructions',
        'video_url',
        'contraindications',
    ];

    protected function casts(): array
    {
        return [
            'contraindications' => 'array',
        ];
    }
}
