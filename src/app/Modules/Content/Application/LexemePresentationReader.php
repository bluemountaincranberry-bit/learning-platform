<?php

namespace App\Modules\Content\Application;

use App\Modules\Content\Application\Contracts\LexemePresentationReaderInterface;
use App\Modules\Content\Domain\Models\Lexeme;
use App\Modules\Content\Domain\Models\LexemeExample;

final class LexemePresentationReader implements LexemePresentationReaderInterface
{
    public function fromLoadedLexeme(?object $lexeme, ?int $primaryContentId, string $translationLanguage): array
    {
        if ($lexeme === null) {
            return ['translation' => null, 'example' => null, 'examples' => [], 'associations' => [], 'contexts' => []];
        }

        if (! $lexeme instanceof Lexeme) {
            throw new \InvalidArgumentException('Expected a loaded lexeme.');
        }

        $translation = Lexeme::pickPrimaryTranslation($lexeme->translations, $primaryContentId, $translationLanguage);
        $example = Lexeme::pickPrimaryExample($lexeme->examples, $primaryContentId);

        return [
            'translation' => $translation?->translation,
            'example' => $example?->example,
            'examples' => Lexeme::pickExamples($lexeme->examples, $primaryContentId)
                ->map(fn (LexemeExample $item): array => [
                    'example' => $item->example,
                    'translation' => $item->translation,
                    'is_primary' => (bool) $item->is_primary,
                ])->values()->all(),
            'associations' => Lexeme::mapAssociations($lexeme->associations),
            'contexts' => $lexeme->contentLinks->map(function ($occurrence): array {
                $content = $occurrence->content;

                return [
                    'content_lexeme_id' => $occurrence->id,
                    'content_id' => $content?->id,
                    'content_title' => $content?->title,
                    'language' => $content?->language,
                    'level' => $content?->level,
                ];
            })->values()->all(),
        ];
    }
}
