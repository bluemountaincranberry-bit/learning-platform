<?php

namespace App\Modules\Content\Application\Contracts;

interface LexemeEnrichmentCatalogInterface
{
    /**
     * @return array{language: string, subject_type: string, subject_id: int, prompt: array{system: string, user: string, schema: array<string, mixed>}, relation_types: array<int, string>}|null
     */
    public function promptContext(int $lexemeId): ?array;

    /**
     * @param  array<string, mixed>  $data
     * @return array{related: int, examples: int, translations: int}
     */
    public function applyAccepted(int $lexemeId, array $data): array;
}
