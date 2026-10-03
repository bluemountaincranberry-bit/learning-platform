<?php

namespace App\Modules\Content\Application\Contracts;

interface SentencePracticeCatalogInterface
{
    /** @return list<array{id: int, content_id: int, word: string, language: ?string, review_state: ?string}> */
    public function learningLexemes(int $userId, ?int $contentId = null): array;

    /** @return list<array{id: int, title: string}> */
    public function grammarRules(int $contentId, ?array $grammarRuleIds = null): array;

    /** @return list<string> */
    public function words(int $contentId): array;

    public function language(int $contentId): string;
}
