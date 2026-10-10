<?php

namespace App\Modules\Interview\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InterviewAnswerVariant extends Model
{
    protected $guarded = [];

    public function revisions(): HasMany
    {
        return $this->hasMany(InterviewAnswerRevision::class, 'answer_variant_id')->latest('id');
    }
}
