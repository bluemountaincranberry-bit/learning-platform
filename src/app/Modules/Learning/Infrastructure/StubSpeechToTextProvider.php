<?php

namespace App\Modules\Learning\Infrastructure;

use App\Modules\Learning\Application\Contracts\SpeechToTextProviderInterface;

class StubSpeechToTextProvider implements SpeechToTextProviderInterface
{
    public function transcribe(string $audioPath, string $language, ?string $filename = null): array
    {
        return ['text' => '', 'confidence' => null, 'provider' => 'stub'];
    }
}
