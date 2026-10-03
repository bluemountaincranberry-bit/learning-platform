<?php

namespace App\Modules\Content\Application;

use App\Modules\Content\Application\Contracts\SelfCheckLexemeCatalogInterface;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\ContentLexeme;
use App\Modules\Content\Domain\Models\Lexeme;

final class SelfCheckLexemeCatalog implements SelfCheckLexemeCatalogInterface
{
    public function forContent(int $contentId, ?array $contentLexemeIds, string $translationLanguage): array
    {
        $query = ContentLexeme::query()
            ->with(['canonicalLexeme.examples', 'canonicalLexeme.translations'])
            ->where('content_id', $contentId)
            ->orderBy('sort_order');

        if ($contentLexemeIds !== null && $contentLexemeIds !== []) {
            $query->whereIn('id', $contentLexemeIds);
        }

        return $query->get()->mapWithKeys(function (ContentLexeme $occurrence) use ($translationLanguage): array {
            $lexeme = $occurrence->canonicalLexeme;
            $translation = $lexeme === null ? null : Lexeme::pickPrimaryTranslation($lexeme->translations, $occurrence->content_id, $translationLanguage);
            $example = $lexeme === null ? null : Lexeme::pickPrimaryExample($lexeme->examples, $occurrence->content_id);
            $examples = $lexeme === null ? collect() : Lexeme::pickExamples($lexeme->examples, $occurrence->content_id);

            return [(int) $occurrence->id => [
                'content_id' => (int) $occurrence->content_id,
                'content_lexeme_id' => (int) $occurrence->id,
                'canonical_lexeme_id' => $occurrence->lexeme_id !== null ? (int) $occurrence->lexeme_id : null,
                'lexeme_display' => (string) $occurrence->text,
                'item_key' => "{$occurrence->type}:{$occurrence->text}",
                'part_of_speech' => $lexeme?->part_of_speech,
                'level' => $lexeme?->level,
                'translation' => $translation?->translation,
                'example' => $example?->example,
                'examples' => $examples->map(fn ($item): array => [
                    'example' => $item->example,
                    'translation' => $item->translation,
                    'is_primary' => (bool) $item->is_primary,
                ])->values()->all(),
            ]];
        })->all();
    }

    public function language(int $contentId): string
    {
        return (string) Content::query()->findOrFail($contentId)->language;
    }
}
