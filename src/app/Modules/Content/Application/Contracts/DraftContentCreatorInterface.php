<?php

namespace App\Modules\Content\Application\Contracts;

interface DraftContentCreatorInterface
{
    /** @return array<int, string> */
    public function types(): array;

    /** @return array<int, string> */
    public function levels(): array;

    /**
     * @param  array{title: string, type: string, language: string, level: ?string, source_text: string, created_by: int}  $data
     * @return array{id: int, title: string, status: string}
     */
    public function create(array $data): array;
}
