<?php

namespace App\Modules\Content\Domain\Models;

use App\Support\HasRevisions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GrammarRule extends Model
{
    use HasRevisions;

    protected $guarded = [];

    protected array $revisionable = ['title', 'summary', 'body', 'status', 'level'];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_REVIEW = 'review';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [self::STATUS_DRAFT, self::STATUS_REVIEW, self::STATUS_PUBLISHED, self::STATUS_ARCHIVED];

    public function topic(): BelongsTo
    {
        return $this->belongsTo(GrammarTopic::class, 'topic_id');
    }

    public function lexemes(): BelongsToMany
    {
        return $this->belongsToMany(Lexeme::class, 'grammar_rule_lexeme')
            ->withPivot('sort_order')->withTimestamps()->orderByPivot('sort_order');
    }

    public function examples(): HasMany
    {
        return $this->hasMany(GrammarRuleExample::class)->orderBy('sort_order');
    }

    public function contentLinks(): HasMany
    {
        return $this->hasMany(ContentRuleLink::class);
    }

    public function exercises(): HasMany
    {
        return $this->hasMany(GrammarRuleExercise::class)->orderBy('sort_order');
    }
}
