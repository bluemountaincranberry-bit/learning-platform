<?php

namespace App\Modules\Learning\Application;

use App\Modules\Learning\Application\Contracts\SpeechToTextProviderInterface;
use App\Modules\Learning\Infrastructure\LocalWhisperSpeechToTextProvider;
use App\Modules\Learning\Infrastructure\OpenAiSpeechToTextProvider;
use InvalidArgumentException;

final class SpeechToTextProviderFactory
{
    public function make(string $provider): SpeechToTextProviderInterface
    {
        return match ($provider) {
            'openai' => new OpenAiSpeechToTextProvider(
                (string) config('ai.openai.api_key', ''),
                (int) config('ai.timeout', 60),
                (string) config('ai.transcription.openai_model', 'gpt-4o-mini-transcribe'),
            ),
            'local_whisper' => new LocalWhisperSpeechToTextProvider(
                (string) config('ai.transcription.local_whisper.base_url', 'http://whisper:8000/v1'),
                (string) config('ai.transcription.local_whisper.model', 'Systran/faster-whisper-small'),
                max(180, (int) config('ai.timeout', 60)),
            ),
            default => throw new InvalidArgumentException('Unsupported speech transcription provider.'),
        };
    }

    /** @return list<array{id: string, label: string}> */
    public function available(): array
    {
        $providers = [];

        if ((string) config('ai.openai.api_key', '') !== '') {
            $providers[] = ['id' => 'openai', 'label' => 'OpenAI transcription'];
        }

        if ((bool) config('ai.transcription.local_whisper.enabled', true)) {
            $providers[] = ['id' => 'local_whisper', 'label' => 'Local Whisper'];
        }

        return $providers;
    }
}
