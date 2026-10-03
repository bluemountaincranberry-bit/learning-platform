<?php

namespace App\Modules\Ai\Application\Data;

final readonly class LessonAnalysisContext
{
    public function __construct(
        public int $id,
        public int $lessonId,
        public int $userId,
        public string $sourceText,
        public string $language,
    ) {}
}
