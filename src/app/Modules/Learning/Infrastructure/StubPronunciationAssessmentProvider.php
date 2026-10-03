<?php

namespace App\Modules\Learning\Infrastructure;

use App\Modules\Learning\Application\Contracts\PronunciationAssessmentProviderInterface;

class StubPronunciationAssessmentProvider implements PronunciationAssessmentProviderInterface
{
    public function assess(string $audioPath, string $targetText, string $language): array
    {
        return ['accuracy' => null, 'fluency' => null, 'completeness' => null, 'prosody' => null, 'words' => [], 'provider' => 'stub'];
    }
}
