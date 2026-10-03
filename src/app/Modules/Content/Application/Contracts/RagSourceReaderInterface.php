<?php

namespace App\Modules\Content\Application\Contracts;

interface RagSourceReaderInterface
{
    /**
     * @param  array<int, int>|null  $ids
     * @return iterable<array{id: int, language: string, level: ?string, title: string, summary: ?string, body: ?string}>
     */
    public function publishedGrammarRules(?array $ids = null): iterable;

    /**
     * @param  array<int, int>|null  $ids
     * @return iterable<array{id: int, language: string, level: ?string, lemma: ?string, example: string, translation: ?string}>
     */
    public function publishedLexemeExamples(?array $ids = null): iterable;
}
