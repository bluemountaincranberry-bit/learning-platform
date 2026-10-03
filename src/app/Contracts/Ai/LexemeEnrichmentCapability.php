<?php

namespace App\Contracts\Ai;

interface LexemeEnrichmentCapability
{
    /**
     * Suggest related words, examples, and translations without persisting them.
     *
     * @return array{related: array<int, array<string, mixed>>, examples: array<int, array{example: string, translation: ?string}>, translations: array<int, array{language: string, translation: string}>}
     */
    public function propose(int $lexemeId): array;
}
