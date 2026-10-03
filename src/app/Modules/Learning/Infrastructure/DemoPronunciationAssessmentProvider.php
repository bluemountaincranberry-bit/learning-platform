<?php

namespace App\Modules\Learning\Infrastructure;

use App\Modules\Learning\Application\Contracts\PronunciationAssessmentProviderInterface;

/** Local-only deterministic feedback so the complete exercise flow works without cloud credentials. */
class DemoPronunciationAssessmentProvider implements PronunciationAssessmentProviderInterface
{
    public function assess(string $audioPath, string $targetText, string $language): array
    {
        return [
            'accuracy' => 82, 'fluency' => 78, 'completeness' => 90, 'prosody' => 76,
            'words' => [], 'provider' => 'demo-local',
        ];
    }
}
