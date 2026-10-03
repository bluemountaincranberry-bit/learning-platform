<?php

namespace App\Modules\Learning\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLexemeSkip extends Model
{
    protected $table = 'user_lexeme_skips';

    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo('App\\Modules\\User\\Models\\User');
    }

}
