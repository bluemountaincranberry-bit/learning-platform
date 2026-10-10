<?php

use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use App\Modules\User\Models\User;

test('openai transcription keeps the uploaded audio filename when forwarding it', function () {
    config()->set('ai.openai.api_key', 'test-key');
    Http::fake([
        'https://api.openai.com/v1/audio/transcriptions' => Http::response(['text' => 'hello there']),
    ]);

    $response = $this->actingAs(User::factory()->create())->post('/api/learning/speech/transcribe', [
        'audio' => UploadedFile::fake()->create('recording.wav', 10, 'audio/wav'),
        'provider' => 'openai',
        'language' => 'en',
    ], ['Accept' => 'application/json']);

    $response->assertOk()->assertJsonPath('transcription.text', 'hello there');

    Http::assertSent(fn (Request $request): bool =>
        $request->url() === 'https://api.openai.com/v1/audio/transcriptions'
        && str_contains($request->body(), 'filename="recording.wav"')
        && preg_match('/Content-Type: audio\//', $request->body()) === 1
    );
});
