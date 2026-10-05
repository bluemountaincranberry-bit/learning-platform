<?php

namespace App\Modules\Learning\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LessonCorrection extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
