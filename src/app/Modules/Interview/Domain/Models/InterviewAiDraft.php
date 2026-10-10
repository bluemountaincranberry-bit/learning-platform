<?php

namespace App\Modules\Interview\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InterviewAiDraft extends Model
{
    protected $guarded = [];

    protected $casts = ['payload' => 'array', 'decided_at' => 'datetime'];

    public function resultQuestion(): BelongsTo
    {
        return $this->belongsTo(InterviewQuestion::class, 'result_question_id');
    }

    public function resultProfile(): BelongsTo
    {
        return $this->belongsTo(InterviewProfile::class, 'result_profile_id');
    }
}
