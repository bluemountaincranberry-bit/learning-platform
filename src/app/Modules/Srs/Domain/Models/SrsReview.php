<?php

namespace App\Modules\Srs\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SrsReview extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'hint_used' => 'boolean',
            'answer_metadata' => 'array',
        ];
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(SrsCard::class, 'srs_card_id');
    }
}
