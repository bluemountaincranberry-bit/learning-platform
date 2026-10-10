<?php

namespace App\Modules\Interview\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InterviewQuestion extends Model
{
    protected $guarded = [];

    public function topic(): BelongsTo
    {
        return $this->belongsTo(InterviewTopic::class, 'topic_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(InterviewTag::class, 'interview_question_tag', 'question_id', 'tag_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(InterviewAnswerVariant::class, 'question_id');
    }
}
