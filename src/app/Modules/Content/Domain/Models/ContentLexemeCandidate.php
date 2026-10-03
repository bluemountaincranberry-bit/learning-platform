<?php

namespace App\Modules\Content\Domain\Models;

use App\Modules\Content\Domain\Models\Lexeme;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentLexemeCandidate extends Model
{
    protected $guarded = [];

    public const TYPE_WORD = 'word';

    public const TYPE_PHRASE = 'phrase';

    public const TYPE_PHRASAL_VERB = 'phrasal_verb';

    public const TYPE_IDIOM = 'idiom';

    public const TYPE_COLLOCATION = 'collocation';

    public const TYPES = [self::TYPE_WORD, self::TYPE_PHRASE, self::TYPE_PHRASAL_VERB, self::TYPE_IDIOM, self::TYPE_COLLOCATION];

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_EDITED = 'edited';

    public const STATUS_APPLIED = 'applied';

    public const STATUSES = [self::STATUS_PENDING, self::STATUS_ACCEPTED, self::STATUS_REJECTED, self::STATUS_EDITED, self::STATUS_APPLIED];

    protected function casts(): array
    {
        return ['confidence' => 'float', 'match_score' => 'float', 'examples' => 'array', 'grammar_features' => 'array'];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo('App\\Modules\\Ai\\Domain\\Models\\AiAnalysisRun', 'ai_analysis_run_id');
    }

    public function matchedLexeme(): BelongsTo
    {
        return $this->belongsTo(Lexeme::class, 'matched_lexeme_id');
    }
}
