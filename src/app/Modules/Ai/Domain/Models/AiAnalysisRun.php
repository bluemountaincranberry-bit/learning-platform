<?php

namespace App\Modules\Ai\Domain\Models;

use App\Modules\Content\Application\Data\CandidateModelReferences;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiAnalysisRun extends Model
{
    protected $guarded = [];

    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUSES = [self::STATUS_PENDING, self::STATUS_RUNNING, self::STATUS_COMPLETED, self::STATUS_FAILED];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'config' => 'array',
            'coverage_pct' => 'float',
            'uncovered_words' => 'array',
            'retried_for_coverage' => 'boolean',
        ];
    }

    public function lexemeCandidates(): HasMany
    {
        return $this->hasMany(CandidateModelReferences::lexemeCandidate(), 'ai_analysis_run_id');
    }

    public function grammarCandidates(): HasMany
    {
        return $this->hasMany(CandidateModelReferences::grammarCandidate(), 'ai_analysis_run_id');
    }
}
