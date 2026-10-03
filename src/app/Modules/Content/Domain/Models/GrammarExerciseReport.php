<?php

namespace App\Modules\Content\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrammarExerciseReport extends Model
{
    protected $guarded = [];

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(GrammarRuleExercise::class, 'grammar_rule_exercise_id');
    }
}
