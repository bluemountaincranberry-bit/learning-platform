<?php

namespace App\Modules\Content\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TranscriptSegmentTranslation extends Model
{
    protected $guarded = [];

    public function segment(): BelongsTo
    {
        return $this->belongsTo(TranscriptSegment::class, 'transcript_segment_id');
    }
}
