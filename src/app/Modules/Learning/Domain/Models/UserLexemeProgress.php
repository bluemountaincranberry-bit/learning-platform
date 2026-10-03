<?php

namespace App\Modules\Learning\Domain\Models;

use App\Modules\Content\Application\Contracts\ContentLexemeReferenceReaderInterface;
use Illuminate\Database\Eloquent\Model;

class UserLexemeProgress extends Model
{
    protected $table = 'user_lexeme_progress';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'learned_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $progress): void {
            if ($progress->lexeme_id === null && $progress->content_lexeme_id !== null) {
                $progress->lexeme_id = app(ContentLexemeReferenceReaderInterface::class)
                    ->canonicalLexemeId($progress->content_lexeme_id);
            }
        });
    }
}
