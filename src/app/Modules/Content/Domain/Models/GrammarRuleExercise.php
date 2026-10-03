<?php

namespace App\Modules\Content\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GrammarRuleExercise extends Model
{
    protected $guarded = [];

    /** Choose the form (Easy): tap one of `options`, checked by `answer_index`. */
    public const TYPE_MULTIPLE_CHOICE = 'multiple_choice';

    /** Build the sentence (Easy): put `tiles` in order, checked against `answer`. */
    public const TYPE_BUILD = 'build';

    /** Fill the gap (Hard): type the missing form. */
    public const TYPE_CLOZE = 'cloze';

    /** Transform (Hard): rewrite the sentence as `instruction` says. */
    public const TYPE_TRANSFORM = 'transform';

    /** Fix the mistake (Hard): type the corrected sentence. */
    public const TYPE_FIX = 'fix';

    /** Round order: easy → hard (docs/product/grammar-exercises-block.md). */
    public const TYPES = [self::TYPE_MULTIPLE_CHOICE, self::TYPE_BUILD, self::TYPE_CLOZE, self::TYPE_TRANSFORM, self::TYPE_FIX];

    public const EASY_TYPES = [self::TYPE_MULTIPLE_CHOICE, self::TYPE_BUILD];

    public const HARD_TYPES = [self::TYPE_CLOZE, self::TYPE_TRANSFORM, self::TYPE_FIX];

    /** Types answered by typing (or arranging) text, graded by GrammarAnswerChecker. */
    public const TEXT_ANSWER_TYPES = [self::TYPE_BUILD, self::TYPE_CLOZE, self::TYPE_TRANSFORM, self::TYPE_FIX];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [self::STATUS_DRAFT, self::STATUS_PUBLISHED, self::STATUS_ARCHIVED];

    public const ORIGIN_ADMIN = 'admin';

    /** Generated on demand for learners; shown without admin review, marked "AI". */
    public const ORIGIN_AI = 'ai';

    public const ORIGINS = [self::ORIGIN_ADMIN, self::ORIGIN_AI];

    /** Reports from this many learners hide an exercise for everyone. */
    public const HIDE_AFTER_REPORTS = 3;

    protected function casts(): array
    {
        return ['options' => 'array', 'accepted_answers' => 'array', 'tiles' => 'array'];
    }

    public static function levelOf(string $type): string
    {
        return in_array($type, self::EASY_TYPES, true) ? 'easy' : 'hard';
    }

    public function grammarRule(): BelongsTo
    {
        return $this->belongsTo(GrammarRule::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(GrammarExerciseReport::class);
    }
}
