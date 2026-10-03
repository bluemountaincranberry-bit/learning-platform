<?php

namespace App\Modules\Content\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TranscriptSegment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['start_ms' => 'integer', 'end_ms' => 'integer'];
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }

    public function lexemes(): BelongsToMany
    {
        return $this->belongsToMany(ContentLexeme::class, 'transcript_segment_lexemes')
            ->withPivot(['start_offset', 'end_offset', 'surface_text', 'match_type', 'confidence'])
            ->withTimestamps();
    }

    public function translations(): HasMany
    {
        return $this->hasMany(TranscriptSegmentTranslation::class);
    }
}
