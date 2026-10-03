<?php

namespace App\Modules\Ai\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Lesson extends Model
{
    protected $guarded = [];

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [self::STATUS_ACTIVE, self::STATUS_ARCHIVED];

    public function conversation(): HasOne
    {
        return $this->hasOne(AgentConversation::class);
    }

    public function analysisRuns(): HasMany
    {
        return $this->hasMany(LessonAnalysisRun::class);
    }

    public function latestAnalysisRun(): HasOne
    {
        return $this->hasOne(LessonAnalysisRun::class)->latestOfMany();
    }

    public function distinctLexemeCandidates(): \Illuminate\Support\Collection
    {
        return LessonLexemeCandidate::query()
            ->whereIn('lesson_analysis_run_id', $this->analysisRuns()->pluck('id'))
            ->orderByDesc('id')->get()->unique('normalized_text')->values();
    }

    public function distinctGrammarCandidates(): \Illuminate\Support\Collection
    {
        return LessonGrammarCandidate::query()
            ->whereIn('lesson_analysis_run_id', $this->analysisRuns()->pluck('id'))
            ->orderByDesc('id')->get()
            ->unique(fn (LessonGrammarCandidate $candidate) => Str::lower(trim($candidate->title)))
            ->values();
    }
}
