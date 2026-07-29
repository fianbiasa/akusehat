<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class UserAiSetting extends Model
{
    protected $fillable = [
        'user_id',
        'provider_id',
        'model_id',
        'api_key', // virtual - see setApiKeyAttribute(); api_key_encrypted is intentionally NOT mass-assignable
        'temperature',
        'is_default',
    ];

    protected $hidden = [
        'api_key_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class, 'provider_id');
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(AiModel::class, 'model_id');
    }

    public function setApiKeyAttribute(?string $value): void
    {
        $this->attributes['api_key_encrypted'] = $value ? Crypt::encryptString($value) : null;
    }

    public function decryptedApiKey(): ?string
    {
        return $this->api_key_encrypted ? Crypt::decryptString($this->api_key_encrypted) : null;
    }
}
