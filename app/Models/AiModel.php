<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiModel extends Model
{
    protected $fillable = [
        'provider_id',
        'name',
        'model_key',
        'context_length',
        'supports_json_mode',
        'input_cost_per_1k',
        'output_cost_per_1k',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'supports_json_mode' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class, 'provider_id');
    }
}
