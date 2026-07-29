<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KbDisease extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'category',
        'description',
        'dietary_restrictions',
        'recommended_exercise',
        'contraindicated_exercise',
        'reference_source',
    ];

    protected function casts(): array
    {
        return [
            'dietary_restrictions' => 'array',
            'recommended_exercise' => 'array',
            'contraindicated_exercise' => 'array',
        ];
    }
}
