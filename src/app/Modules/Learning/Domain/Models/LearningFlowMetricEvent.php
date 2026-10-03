<?php

namespace App\Modules\Learning\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearningFlowMetricEvent extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['success' => 'boolean', 'metadata' => 'array', 'created_at' => 'datetime'];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(LearningFlowProfile::class, 'learning_flow_profile_id');
    }
}
