<?php

use App\Modules\Learning\Infrastructure\AzurePronunciationAssessmentProvider;
use Illuminate\Support\Facades\Http;

test('azure pronunciation adapter maps detailed assessment response', function () {
    Http::fake([
        'https://eastus.stt.speech.microsoft.com/*' => Http::response([
            'DisplayText' => 'hello',
            'NBest' => [[
                'PronunciationAssessment' => [
                    'AccuracyScore' => 91.4, 'FluencyScore' => 83.2,
                    'CompletenessScore' => 100, 'ProsodyScore' => 79.8,
                ],
                'Words' => [['Word' => 'hello', 'PronunciationAssessment' => ['AccuracyScore' => 91]]],
            ]],
        ]),
    ]);
    $path = tempnam(sys_get_temp_dir(), 'azure-audio-');
    $wavPath = $path.'.wav';
    rename($path, $wavPath);
    file_put_contents($wavPath, 'RIFF audio');

    $result = (new AzurePronunciationAssessmentProvider('key', 'eastus'))->assess($wavPath, 'hello', 'en');

    expect($result['provider'])->toBe('azure-speech')
        ->and($result['accuracy'])->toBe(91)
        ->and($result['fluency'])->toBe(83)
        ->and($result['completeness'])->toBe(100)
        ->and($result['prosody'])->toBe(80);
});
