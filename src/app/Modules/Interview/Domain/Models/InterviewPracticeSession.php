<?php

namespace App\Modules\Interview\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class InterviewPracticeSession extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['question_ids' => 'array'];
    }
}
