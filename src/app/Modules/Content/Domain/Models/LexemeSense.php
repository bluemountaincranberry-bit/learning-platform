<?php

namespace App\Modules\Content\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A distinct meaning of a canonical Lexeme. */
class LexemeSense extends Model
{
    protected $guarded = [];

    public function lexeme(): BelongsTo
    {
        return $this->belongsTo(Lexeme::class);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(LexemeTranslation::class)->orderBy('sort_order');
    }

    public function examples(): HasMany
    {
        return $this->hasMany(LexemeExample::class)->orderBy('sort_order');
    }
}
