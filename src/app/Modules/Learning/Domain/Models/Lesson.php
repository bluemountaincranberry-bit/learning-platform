<?php

namespace App\Modules\Learning\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Lesson extends Model
{
    protected $guarded = [];

    protected $casts = [
        'tags' => 'array',
        'lesson_date' => 'date',
    ];

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [self::STATUS_ACTIVE, self::STATUS_ARCHIVED];

    public function analysisRuns(): HasMany
    {
        return $this->hasMany(LessonAnalysisRun::class);
    }

    public function lexemeCandidates(): HasMany
    {
        return $this->hasMany(LessonLexemeCandidate::class);
    }

    public function grammarCandidates(): HasMany
    {
        return $this->hasMany(LessonGrammarCandidate::class);
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(LessonCorrection::class);
    }

    public function latestAnalysisRun(): HasOne
    {
        return $this->hasOne(LessonAnalysisRun::class)->latestOfMany();
    }

    public function distinctLexemeCandidates(): \Illuminate\Support\Collection
    {
        return $this->lexemeCandidates()
            ->orderByDesc('id')->get()->unique('normalized_text')->values();
    }

    public function distinctGrammarCandidates(): \Illuminate\Support\Collection
    {
        return $this->grammarCandidates()
            ->orderByDesc('id')->get()
            ->unique(fn (LessonGrammarCandidate $candidate) => Str::lower(trim($candidate->title)))
            ->values();
    }
}
