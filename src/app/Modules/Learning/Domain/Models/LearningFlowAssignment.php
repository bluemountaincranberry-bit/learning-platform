<?php

namespace App\Modules\Learning\Domain\Models;

use App\Support\HasRevisions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearningFlowAssignment extends Model
{
    use HasFactory, HasRevisions;

    protected array $revisionable = ['learning_flow_profile_id', 'user_id', 'language', 'level', 'learning_goal', 'priority', 'starts_at', 'ends_at'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(LearningFlowProfile::class, 'learning_flow_profile_id');
    }
}
