<?php

namespace App\Modules\Learning\Application\Contracts;

interface PronunciationAssessmentProviderInterface
{
    /** @return array{accuracy: ?int, fluency: ?int, completeness: ?int, prosody: ?int, words: array<int, mixed>, provider: string} */
    public function assess(string $audioPath, string $targetText, string $language): array;
}
