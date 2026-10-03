<?php

namespace App\Modules\Learning\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class LearningPointEvent extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['multiplier' => 'float', 'metadata' => 'array'];
    }
}
