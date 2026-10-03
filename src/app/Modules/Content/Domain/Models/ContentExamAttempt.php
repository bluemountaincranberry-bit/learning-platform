<?php

namespace App\Modules\Content\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentExamAttempt extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['passed' => 'boolean', 'score_pct' => 'float', 'pass_threshold_pct' => 'float', 'items' => 'array', 'completed_at' => 'datetime'];
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }
}
