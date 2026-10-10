<?php

namespace App\Modules\Interview\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InterviewProfile extends Model
{
    protected $guarded = [];

    protected $casts = ['skills' => 'array', 'projects' => 'array', 'experience_stories' => 'array'];

    public function milestones(): HasMany
    {
        return $this->hasMany(InterviewMilestone::class, 'profile_id')->orderBy('target_date')->orderBy('id');
    }
}
