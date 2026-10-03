<?php

namespace App\Modules\Learning\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExerciseAttempt extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_correct' => 'boolean', 'hint_used' => 'boolean', 'provider_result' => 'array', 'audio_expires_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo((string) config('auth.providers.users.model'));
    }
}
