<?php

namespace App\Modules\Ai\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class GrammarRuleEmbedding extends Model
{
    protected $guarded = [];

    protected $casts = [
        'embedding' => 'array',
    ];
}
