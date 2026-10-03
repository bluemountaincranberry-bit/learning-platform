<?php

namespace App\Modules\Content\Domain\Models;

use App\Modules\Content\Domain\Models\GrammarRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentGrammarCandidate extends Model
{
    protected $guarded = [];

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_EDITED = 'edited';

    public const STATUS_APPLIED = 'applied';

    public const STATUSES = [self::STATUS_PENDING, self::STATUS_ACCEPTED, self::STATUS_REJECTED, self::STATUS_EDITED, self::STATUS_APPLIED];

    protected function casts(): array
    {
        return ['confidence' => 'float', 'match_score' => 'float'];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo('App\\Modules\\Ai\\Domain\\Models\\AiAnalysisRun', 'ai_analysis_run_id');
    }

    public function matchedGrammarRule(): BelongsTo
    {
        return $this->belongsTo(GrammarRule::class, 'matched_grammar_rule_id');
    }
}
