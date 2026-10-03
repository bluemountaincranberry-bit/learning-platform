<?php

namespace App\Modules\Content\Domain\Models;

use App\Modules\Content\Application\CanonicalLexemeSyncService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentLexeme extends Model
{
    protected $guarded = [];

    public const TYPE_WORD = 'word';

    public const TYPE_PHRASE = 'phrase';

    public const ORIGIN_TOKENIZER = 'tokenizer';

    public const ORIGIN_AI = 'ai';

    public const ORIGIN_MANUAL = 'manual';

    protected function casts(): array
    {
        return [
            'grammar_features' => 'array',
        ];
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }

    protected static function booted(): void
    {
        static::created(function (self $contentLexeme): void {
            if ($contentLexeme->lexeme_id === null) {
                app(CanonicalLexemeSyncService::class)->sync($contentLexeme);
            }
        });
    }

    public function canonicalLexeme(): BelongsTo
    {
        return $this->belongsTo(Lexeme::class, 'lexeme_id');
    }

    public function sense(): BelongsTo
    {
        return $this->belongsTo(LexemeSense::class, 'lexeme_sense_id');
    }
}
