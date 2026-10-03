<?php

namespace App\Modules\Learning\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class UserGrammarRule extends Model
{
    protected $guarded = [];

    public const STATUS_LEARNING = 'learning';

    public const STATUS_LEARNED = 'learned';

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'learned_at' => 'datetime',
            'confidence_manual' => 'float',
            'confidence_calculated' => 'float',
            'confidence_calculated_at' => 'datetime',
        ];
    }
}
