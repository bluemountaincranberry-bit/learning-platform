<?php

namespace App\Modules\Content\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Lexeme extends Model
{
    protected $guarded = [];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_REVIEW = 'review';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [self::STATUS_DRAFT, self::STATUS_REVIEW, self::STATUS_PUBLISHED, self::STATUS_ARCHIVED];

    /** @var array<string, string> */
    public const PARTS_OF_SPEECH = [
        'noun' => 'Noun', 'verb' => 'Verb', 'adjective' => 'Adjective',
        'adverb' => 'Adverb', 'phrase' => 'Phrase', 'idiom' => 'Idiom',
        'preposition' => 'Preposition', 'conjunction' => 'Conjunction',
        'pronoun' => 'Pronoun', 'interjection' => 'Interjection', 'other' => 'Other',
    ];

    public function rules(): BelongsToMany
    {
        return $this->belongsToMany(GrammarRule::class, 'grammar_rule_lexeme')
            ->withPivot('sort_order')->withTimestamps()->orderByPivot('sort_order');
    }

    public function examples(): HasMany
    {
        return $this->hasMany(LexemeExample::class)->orderBy('sort_order');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(LexemeTranslation::class)->orderBy('sort_order');
    }

    public function senses(): HasMany
    {
        return $this->hasMany(LexemeSense::class)->orderBy('sort_order');
    }

    public function associations(): HasMany
    {
        return $this->hasMany(LexemeAssociation::class)->orderBy('sort_order');
    }

    public function relatedTo(): HasMany
    {
        return $this->hasMany(LexemeAssociation::class, 'related_lexeme_id')->orderBy('sort_order');
    }

    public function contentLinks(): HasMany
    {
        return $this->hasMany(ContentLexeme::class, 'lexeme_id');
    }

    public function explanations(): HasMany
    {
        return $this->hasMany(LexemeExplanation::class);
    }

    /** @param Collection<int, LexemeExample> $examples */
    public static function pickPrimaryExample(Collection $examples, ?int $contentId): ?LexemeExample
    {
        if ($contentId !== null) {
            $scoped = $examples->first(fn (LexemeExample $example) => $example->content_id === $contentId && $example->is_primary);
            if ($scoped !== null) {
                return $scoped;
            }
        }

        return $examples->first(fn (LexemeExample $example) => $example->content_id === null && $example->is_primary);
    }

    /** @param Collection<int, LexemeTranslation> $translations */
    public static function pickPrimaryTranslation(Collection $translations, ?int $contentId, string $language): ?LexemeTranslation
    {
        $translations = $translations->where('language', $language);
        if ($contentId !== null) {
            $scoped = $translations->first(fn (LexemeTranslation $translation) => $translation->content_id === $contentId && $translation->is_primary);
            if ($scoped !== null) {
                return $scoped;
            }
        }

        return $translations->first(fn (LexemeTranslation $translation) => $translation->content_id === null && $translation->is_primary);
    }

    /** @param Collection<int, LexemeExample> $examples */
    public static function pickExamples(Collection $examples, ?int $contentId, int $limit = 3): Collection
    {
        $scoped = $contentId !== null ? $examples->where('content_id', $contentId)->sortBy('sort_order')->values() : collect();
        $global = $examples->where('content_id', null)->sortBy('sort_order')->values();

        return $scoped->concat($global)->unique('id')->take($limit)->values();
    }

    /** @param Collection<int, LexemeAssociation> $associations */
    public static function mapAssociations(Collection $associations, int $limit = 12): array
    {
        return $associations->filter(fn (LexemeAssociation $association) => $association->relatedLexeme?->lemma !== null)
            ->take($limit)->map(fn (LexemeAssociation $association) => [
                'lemma' => $association->relatedLexeme->lemma,
                'type' => $association->type,
            ])->values()->all();
    }
}
