<?php

namespace App\Modules\Learning\Infrastructure;

use App\Exceptions\AiClientException;
use App\Modules\Learning\Application\Contracts\SpeechToTextProviderInterface;
use Illuminate\Support\Facades\Http;

class OpenAiSpeechToTextProvider implements SpeechToTextProviderInterface
{
    public function __construct(private readonly string $apiKey, private readonly int $timeout = 60, private readonly string $model = 'gpt-4o-mini-transcribe') {}

    public function transcribe(string $audioPath, string $language, ?string $filename = null): array
    {
        if ($this->apiKey === '') throw new AiClientException('OpenAI API key is not configured.');
        $response = Http::withToken($this->apiKey)->timeout($this->timeout)
            ->attach('file', fopen($audioPath, 'rb'), basename($filename ?? $audioPath))
            ->post('https://api.openai.com/v1/audio/transcriptions', ['model' => $this->model, 'language' => $language, 'response_format' => 'json']);
        if ($response->failed()) throw new AiClientException((string) ($response->json('error.message') ?? $response->reason()));
        $text = trim((string) $response->json('text'));
        if ($text === '') throw new AiClientException('OpenAI returned an empty transcript.');

        return ['text' => $text, 'confidence' => null, 'provider' => 'openai'];
    }
}
