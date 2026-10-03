<?php

namespace App\Modules\Learning\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Stores the latest self-check result used to prioritize context practice. */
class UserLexemeContextCheck extends Model
{
    protected $table = 'user_lexeme_context_checks';

    protected $guarded = [];

    public const RESULT_CORRECT = 'correct';

    public const RESULT_NEEDS_WORK = 'needs_work';

    protected function casts(): array
    {
        return [
            'checked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo('App\\Modules\\User\\Models\\User');
    }

}
