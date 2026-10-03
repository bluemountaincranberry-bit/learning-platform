<?php

namespace App\Modules\Content\Application\Contracts;

interface LexemeCatalogSearchInterface
{
    /** @return array<int, array{id: int, lemma: string, language: string, level: ?string, status: string}> */
    public function search(string $language, string $query, int $limit): array;

    /** @return array<int, array{lemma: string, language: string, level: ?string, part_of_speech: ?string}> */
    public function searchPublished(string $query, ?string $language, int $limit): array;
}
