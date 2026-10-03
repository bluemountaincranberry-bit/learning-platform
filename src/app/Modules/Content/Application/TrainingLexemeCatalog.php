<?php

namespace App\Modules\Content\Application;

use App\Modules\Content\Application\Contracts\TrainingLexemeCatalogInterface;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\ContentLexeme;
use App\Modules\Content\Domain\Models\Lexeme;

final class TrainingLexemeCatalog implements TrainingLexemeCatalogInterface
{
    public function presentations(array $contentLexemeIds, string $translationLanguage): array
    {
        return ContentLexeme::query()
            ->with(['canonicalLexeme.examples', 'canonicalLexeme.translations', 'canonicalLexeme.associations.relatedLexeme'])
            ->whereIn('id', $contentLexemeIds)
            ->whereHas('content', fn ($query) => $query->whereIn('status', Content::PUBLIC_STATUSES))
            ->get()
            ->mapWithKeys(function (ContentLexeme $occurrence) use ($translationLanguage): array {
                $lexeme = $occurrence->canonicalLexeme;
                $translation = $lexeme === null ? null : Lexeme::pickPrimaryTranslation($lexeme->translations, $occurrence->content_id, $translationLanguage);
                $example = $lexeme === null ? null : Lexeme::pickPrimaryExample($lexeme->examples, $occurrence->content_id);
                $examples = $lexeme === null ? collect() : Lexeme::pickExamples($lexeme->examples, $occurrence->content_id);

                return [$occurrence->id => [
                    'content_id' => (int) $occurrence->content_id,
                    'content_lexeme_id' => (int) $occurrence->id,
                    'lexeme_display' => $occurrence->text,
                    'part_of_speech' => $lexeme?->part_of_speech,
                    'level' => $lexeme?->level,
                    'translation' => $translation?->translation,
                    'example' => $example?->example,
                    'examples' => $examples->map(fn ($item): array => [
                        'example' => $item->example,
                        'translation' => $item->translation,
                        'is_primary' => (bool) $item->is_primary,
                    ])->values()->all(),
                    'associations' => $lexeme === null ? [] : Lexeme::mapAssociations($lexeme->associations),
                ]];
            })->all();
    }
}
