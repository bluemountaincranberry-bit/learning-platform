<?php

namespace App\Modules\Learning\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLexemeConfidence extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'recognition' => 'integer',
            'recall' => 'integer',
            'production' => 'integer',
            'listening' => 'integer',
            'speaking' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo('App\\Modules\\User\\Models\\User');
    }

}
