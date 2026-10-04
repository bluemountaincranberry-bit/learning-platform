<?php

namespace App\Modules\Content\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A learner hid this example for themselves (VIK-39); the catalog row stays. */
class GrammarRuleExampleHide extends Model
{
    protected $guarded = [];

    public function example(): BelongsTo
    {
        return $this->belongsTo(GrammarRuleExample::class, 'grammar_rule_example_id');
    }
}
