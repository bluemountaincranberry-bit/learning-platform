<?php

namespace App\Modules\Content\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LexemeExample extends Model
{
    protected $guarded = [];

    protected $casts = ['is_primary' => 'boolean'];

    public function lexeme(): BelongsTo
    {
        return $this->belongsTo(Lexeme::class);
    }

    public function sense(): BelongsTo
    {
        return $this->belongsTo(LexemeSense::class, 'lexeme_sense_id');
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }
}
