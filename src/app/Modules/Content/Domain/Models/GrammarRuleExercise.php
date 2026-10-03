<?php

namespace App\Modules\Content\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrammarRuleExercise extends Model
{
    protected $guarded = [];

    public const TYPE_CLOZE = 'cloze';

    public const TYPE_MULTIPLE_CHOICE = 'multiple_choice';

    public const TYPES = [self::TYPE_CLOZE, self::TYPE_MULTIPLE_CHOICE];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [self::STATUS_DRAFT, self::STATUS_PUBLISHED, self::STATUS_ARCHIVED];

    protected function casts(): array
    {
        return ['options' => 'array'];
    }

    public function grammarRule(): BelongsTo
    {
        return $this->belongsTo(GrammarRule::class);
    }
}
