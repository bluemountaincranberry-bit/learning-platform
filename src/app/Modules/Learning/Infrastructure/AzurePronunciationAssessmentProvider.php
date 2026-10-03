<?php

namespace App\Modules\Learning\Infrastructure;

use App\Exceptions\AiClientException;
use App\Modules\Learning\Application\Contracts\PronunciationAssessmentProviderInterface;
use Illuminate\Support\Facades\Http;

class AzurePronunciationAssessmentProvider implements PronunciationAssessmentProviderInterface
{
    public function __construct(
        private readonly string $key,
        private readonly string $region,
        private readonly int $timeout = 60,
    ) {}

    public function assess(string $audioPath, string $targetText, string $language): array
    {
        if ($this->key === '' || $this->region === '') {
            throw new AiClientException('Azure Speech credentials are not configured.');
        }

        $locale = str_contains($language, '-') ? $language : match (strtolower($language)) {
            'en' => 'en-US', 'es' => 'es-ES', 'de' => 'de-DE', 'fr' => 'fr-FR', 'it' => 'it-IT',
            'pt' => 'pt-BR', 'pl' => 'pl-PL', 'ru' => 'ru-RU', default => $language,
        };
        $extension = strtolower(pathinfo($audioPath, PATHINFO_EXTENSION));
        $contentType = $extension === 'wav' ? 'audio/wav; codecs=audio/pcm; samplerate=16000' : match ($extension) {
            'ogg' => 'audio/ogg; codecs=opus', 'mp3' => 'audio/mpeg', default => 'audio/webm; codecs=opus',
        };
        $assessment = base64_encode(json_encode([
            'ReferenceText' => $targetText,
            'GradingSystem' => 'HundredMark',
            'Granularity' => 'Phoneme',
            'Dimension' => 'Comprehensive',
        ], JSON_THROW_ON_ERROR));

        $response = Http::withHeaders([
            'Ocp-Apim-Subscription-Key' => $this->key,
            'Pronunciation-Assessment' => $assessment,
            'Content-Type' => $contentType,
            'Accept' => 'application/json',
        ])->timeout($this->timeout)->withBody((string) file_get_contents($audioPath), $contentType)
            ->post("https://{$this->region}.stt.speech.microsoft.com/speech/recognition/conversation/cognitiveservices/v1?language=".rawurlencode($locale).'&format=detailed');

        if ($response->failed()) {
            throw new AiClientException((string) ($response->json('error') ?? $response->reason()), $response->status());
        }

        $json = $response->json();
        $nBest = $json['NBest'][0] ?? [];

        return [
            'accuracy' => $this->score($nBest['PronunciationAssessment']['AccuracyScore'] ?? null),
            'fluency' => $this->score($nBest['PronunciationAssessment']['FluencyScore'] ?? null),
            'completeness' => $this->score($nBest['PronunciationAssessment']['CompletenessScore'] ?? null),
            'prosody' => $this->score($nBest['PronunciationAssessment']['ProsodyScore'] ?? null),
            'words' => $nBest['Words'] ?? [],
            'recognized_text' => $json['DisplayText'] ?? null,
            'provider' => 'azure-speech',
        ];
    }

    private function score(mixed $value): ?int
    {
        return is_numeric($value) ? (int) round((float) $value) : null;
    }
}
