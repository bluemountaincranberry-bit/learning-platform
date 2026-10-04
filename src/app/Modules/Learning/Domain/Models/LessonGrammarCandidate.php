<?php

namespace App\Modules\Learning\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonGrammarCandidate extends Model
{
    protected $guarded = [];

    public const STATUS_PENDING = 'pending';

    public const STATUS_LINKED = 'linked';

    public const STATUS_NEW = 'new';

    public const STATUSES = [self::STATUS_PENDING, self::STATUS_LINKED, self::STATUS_NEW];

    protected function casts(): array
    {
        return ['confidence' => 'float', 'match_score' => 'float'];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(LessonAnalysisRun::class, 'lesson_analysis_run_id');
    }
}
