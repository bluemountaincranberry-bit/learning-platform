<?php

namespace App\Modules\Content\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LexemeExplanation extends Model
{
    protected $guarded = [];

    public function lexeme(): BelongsTo
    {
        return $this->belongsTo(Lexeme::class);
    }
}
