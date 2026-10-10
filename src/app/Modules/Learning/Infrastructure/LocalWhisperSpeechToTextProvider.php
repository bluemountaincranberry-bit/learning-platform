<?php

namespace App\Modules\Learning\Infrastructure;

use App\Exceptions\AiClientException;
use App\Modules\Learning\Application\Contracts\SpeechToTextProviderInterface;
use Illuminate\Support\Facades\Http;

/** OpenAI-compatible local Speaches/faster-whisper endpoint. */
class LocalWhisperSpeechToTextProvider implements SpeechToTextProviderInterface
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $model,
        private readonly int $timeout = 120,
    ) {}

    public function transcribe(string $audioPath, string $language, ?string $filename = null): array
    {
        if ($this->baseUrl === '') {
            throw new AiClientException('The local Whisper service is not configured.');
        }

        $response = Http::timeout($this->timeout)
            ->attach('file', fopen($audioPath, 'rb'), basename($filename ?? $audioPath))
            ->post(rtrim($this->baseUrl, '/').'/audio/transcriptions', [
                'model' => $this->model,
                'language' => $language,
                'response_format' => 'json',
            ]);

        if ($response->failed()) {
            throw new AiClientException((string) ($response->json('error.message') ?? $response->reason()));
        }

        $text = trim((string) $response->json('text'));
        if ($text === '') {
            throw new AiClientException('The local Whisper service returned an empty transcript.');
        }

        return ['text' => $text, 'confidence' => null, 'provider' => 'local_whisper'];
    }
}
