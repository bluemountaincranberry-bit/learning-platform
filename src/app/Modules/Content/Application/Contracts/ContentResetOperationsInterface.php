<?php

namespace App\Modules\Content\Application\Contracts;

interface ContentResetOperationsInterface
{
    /** @return array{deleted: int, preserved: int} */
    public function removeUnlearnedAiLexemes(int $contentId): array;

    public function resetFullContent(int $contentId): int;

    public function rerun(int $contentId, bool $reprocessContent): void;
}
