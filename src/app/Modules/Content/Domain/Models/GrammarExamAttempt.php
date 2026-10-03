<?php

namespace App\Modules\Content\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrammarExamAttempt extends Model
{
    protected $guarded = [];

    public const TYPE_PRE = 'pre';

    public const TYPE_POST = 'post';

    public const TYPES = [self::TYPE_PRE, self::TYPE_POST];

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
