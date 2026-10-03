<?php

namespace App\Modules\Content\Application\Contracts;

interface LexemeLearningStateWriterInterface
{
    public function markLearned(int $userId, int $lexemeId, int $contentLexemeId): void;

    public function unmarkLearned(int $userId, int $lexemeId): void;

    public function skip(int $userId, int $lexemeId): void;

    public function unskip(int $userId, int $lexemeId): void;
}
