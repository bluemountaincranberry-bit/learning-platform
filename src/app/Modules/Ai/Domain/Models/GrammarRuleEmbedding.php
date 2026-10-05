<?php

namespace App\Modules\Ai\Domain\Models;

use App\Modules\Content\Domain\Models\GrammarRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrammarRuleEmbedding extends Model
{
    protected $guarded = [];

    protected $casts = [
        'embedding' => 'array',
    ];

    public function grammarRule(): BelongsTo
    {
        return $this->belongsTo(GrammarRule::class);
    }
}
