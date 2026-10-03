<?php

namespace App\Modules\Learning\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearningRetry extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['available_at' => 'datetime', 'metadata' => 'array'];
    }

    public function sourceExerciseAttempt(): BelongsTo
    {
        return $this->belongsTo(ExerciseAttempt::class, 'source_exercise_attempt_id');
    }
}
