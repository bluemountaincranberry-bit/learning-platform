<?php

namespace App\Modules\Interview\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InterviewCoachingObservation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['examples' => 'array'];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(InterviewProfile::class, 'profile_id');
    }
}
