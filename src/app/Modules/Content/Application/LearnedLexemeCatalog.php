<?php

namespace App\Modules\Content\Application;

use App\Modules\Content\Application\Contracts\LearnedLexemeCatalogInterface;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\ContentLexeme;
use App\Modules\Content\Domain\Models\Lexeme;

final class LearnedLexemeCatalog implements LearnedLexemeCatalogInterface
{
    public function __construct(private LexemePresentationReader $presentations) {}

    public function filterPublicLexemeIds(array $lexemeIds, array $filters): array
    {
        return Lexeme::query()->whereIn('id', $lexemeIds)
            ->when(! empty($filters['language']), fn ($query) => $query->where('language', $filters['language']))
            ->whereHas('contentLinks.content', function ($query) use ($filters): void {
                $query->whereIn('status', Content::PUBLIC_STATUSES)
                    ->when(! empty($filters['content_id']), fn ($contentQuery) => $contentQuery->whereKey((int) $filters['content_id']));
            })->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }

    public function presentations(array $entries, string $translationLanguage): array
    {
        $lexemes = Lexeme::query()->with(['examples', 'translations', 'associations.relatedLexeme', 'contentLinks.content'])
            ->whereIn('id', array_column($entries, 'lexeme_id'))->get()->keyBy('id');
        $occurrences = ContentLexeme::query()->whereIn('id', array_filter(array_column($entries, 'content_lexeme_id')))->get()->keyBy('id');
        $result = [];
        foreach ($entries as $entry) {
            $lexeme = $lexemes->get($entry['lexeme_id']);
            $occurrence = $entry['content_lexeme_id'] === null ? null : $occurrences->get($entry['content_lexeme_id']);
            $result[$entry['progress_id']] = [
                'lexeme_id' => $lexeme?->id,
                'content_lexeme_id' => $occurrence?->id,
                'lexeme' => $occurrence?->text,
                'item_key' => $occurrence === null ? null : "{$occurrence->type}:{$occurrence->text}",
                ...$this->presentations->fromLoadedLexeme($lexeme, $occurrence?->content_id, $translationLanguage),
            ];
        }

        return $result;
    }
}
