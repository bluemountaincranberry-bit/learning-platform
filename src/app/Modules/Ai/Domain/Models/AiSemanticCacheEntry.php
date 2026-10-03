<?php

namespace App\Modules\Ai\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class AiSemanticCacheEntry extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'embedding' => 'array',
            'expires_at' => 'datetime',
        ];
    }
}
