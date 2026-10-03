<?php

namespace App\Modules\Content\Application\Contracts;

interface EmbeddingSourceReaderInterface
{
    /** @param array<int, int> $ids
     * @return array<int, array{id: int, text: string}>
     */
    public function contentLexemes(array $ids): array;

    /** @param array<int, int> $ids
     * @return array<int, array{id: int, text: string}>
     */
    public function canonicalLexemes(array $ids): array;

    /** @param array<int, int> $ids
     * @return array<int, array{id: int, text: string}>
     */
    public function grammarRules(array $ids): array;
}
