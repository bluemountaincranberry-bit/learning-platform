<?php

namespace App\Modules\Content\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrammarRuleExample extends Model
{
    protected $guarded = [];

    public const ORIGIN_ADMIN = 'admin';

    public const ORIGIN_AI = 'ai';

    public const ORIGIN_CONTENT = 'content';

    public const KIND_AFFIRMATIVE = 'affirmative';

    public const KIND_NEGATIVE = 'negative';

    public const KIND_QUESTION = 'question';

    public const KIND_MISTAKE = 'mistake';

    public const KINDS = [self::KIND_AFFIRMATIVE, self::KIND_NEGATIVE, self::KIND_QUESTION, self::KIND_MISTAKE];

    protected $casts = [
        'is_primary' => 'boolean',
        // [[start, end], ...] — character (code point) offsets into `example`.
        'target_spans' => 'array',
    ];

    protected static function booted(): void
    {
        // Spans point into the old sentence; an edited sentence shows unmarked
        // until it is marked again, never with a wrong highlight.
        // Examples written from a video/lesson (AiCandidateApplyService etc.)
        // set content_id but not origin.
        static::creating(function (self $example): void {
            if ($example->content_id !== null && $example->origin === null) {
                $example->origin = self::ORIGIN_CONTENT;
            }
        });

        static::saving(function (self $example): void {
            if ($example->exists && $example->isDirty('example') && ! $example->isDirty('target_spans')) {
                $example->target_spans = null;
            }
        });
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
