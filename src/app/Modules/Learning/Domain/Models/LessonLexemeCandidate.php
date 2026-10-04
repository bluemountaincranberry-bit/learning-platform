<?php

namespace App\Modules\Learning\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonLexemeCandidate extends Model
{
    protected $guarded = [];

    public const TYPE_WORD = 'word';

    public const TYPE_PHRASE = 'phrase';

    public const TYPE_PHRASAL_VERB = 'phrasal_verb';

    public const TYPE_IDIOM = 'idiom';

    public const TYPE_COLLOCATION = 'collocation';

    public const TYPES = [self::TYPE_WORD, self::TYPE_PHRASE, self::TYPE_PHRASAL_VERB, self::TYPE_IDIOM, self::TYPE_COLLOCATION];

    public const STATUS_PENDING = 'pending';

    public const STATUS_MATCHED = 'matched';

    public const STATUS_NEW = 'new';

    public const STATUSES = [self::STATUS_PENDING, self::STATUS_MATCHED, self::STATUS_NEW];

    protected function casts(): array
    {
        return ['confidence' => 'float', 'match_score' => 'float', 'examples' => 'array'];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(LessonAnalysisRun::class, 'lesson_analysis_run_id');
    }
}
