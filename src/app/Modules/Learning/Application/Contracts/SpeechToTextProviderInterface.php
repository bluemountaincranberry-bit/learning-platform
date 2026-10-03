<?php

namespace App\Modules\Learning\Application\Contracts;

interface SpeechToTextProviderInterface
{
    /** @return array{text: string, confidence: ?float, provider: string} */
    public function transcribe(string $audioPath, string $language): array;
}
