<?php

namespace App\Modules\Content\Application\Contracts;

interface LexemePresentationReaderInterface
{
    /**
     * @return array{translation: ?string, example: ?string, examples: array<int, array{example: string, translation: ?string, is_primary: bool}>, associations: array<int, array{lemma: string, type: string}>, contexts: array<int, array<string, mixed>>}
     */
    public function fromLoadedLexeme(?object $lexeme, ?int $primaryContentId, string $translationLanguage): array;
}
