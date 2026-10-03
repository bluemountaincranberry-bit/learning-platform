<?php

namespace App\Modules\Content\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClozeExample extends Model
{
    protected $guarded = [];

    protected $casts = ['distractors' => 'array', 'last_used_at' => 'datetime'];

    public function contentLexeme(): BelongsTo
    {
        return $this->belongsTo(ContentLexeme::class);
    }
}
