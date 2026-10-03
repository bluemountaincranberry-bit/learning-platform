<?php

namespace App\Modules\Content\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrammarExamAttempt extends Model
{
    protected $guarded = [];

    public const TYPE_PRE = 'pre';

    public const TYPE_POST = 'post';

    /** One finished grammar practice round on the rule page (VIK-31). */
    public const TYPE_PRACTICE = 'practice';

    /** Types the content grammar warm-up may submit. */
    public const WARMUP_TYPES = [self::TYPE_PRE, self::TYPE_POST];

    protected function casts(): array
    {
        return ['score_pct' => 'float', 'items' => 'array', 'completed_at' => 'datetime'];
    }

    public function grammarRule(): BelongsTo
    {
        return $this->belongsTo(GrammarRule::class);
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }
}
