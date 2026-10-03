<?php

use App\Exceptions\AiClientException;
use App\Modules\Ai\Infrastructure\OllamaClient;
use App\Modules\Ai\Infrastructure\OpenAiClient;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('OpenAiClient completeJson decodes JSON content into an array', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [
                ['message' => ['content' => '{"words":["run","get up"]}']],
            ],
        ], 200),
    ]);

    $client = new OpenAiClient('test-key', 10);
    $result = $client->completeJson('system', 'user', ['words' => 'array']);

    expect($result)->toBe(['words' => ['run', 'get up']]);

    Http::assertSent(function ($request) {
        return $request['response_format']['type'] === 'json_object'
            && str_contains($request['messages'][0]['content'], 'Respond with a single JSON object');
    });
});

test('OpenAiClient completeJson throws when API key is empty', function () {
    $client = new OpenAiClient('', 10);

    $client->completeJson('system', 'user');
})->throws(AiClientException::class, 'OpenAI API key is not configured');

test('OpenAiClient completeJson throws on non-JSON content', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [
                ['message' => ['content' => 'not json']],
            ],
        ], 200),
    ]);

    $client = new OpenAiClient('test-key', 10);

    $client->completeJson('system', 'user');
})->throws(AiClientException::class, 'OpenAI API returned invalid JSON');

test('OllamaClient completeJson decodes JSON content into an array', function () {
    Http::fake([
        '*/api/chat' => Http::response([
            'message' => ['content' => '{"words":["run"]}'],
        ], 200),
    ]);

    $client = new OllamaClient('http://localhost:11434', 'llama3.2', 10);
    $result = $client->completeJson('system', 'user', ['words' => 'array']);

    expect($result)->toBe(['words' => ['run']]);

    Http::assertSent(function ($request) {
        return $request['format'] === 'json'
            && str_contains($request['messages'][0]['content'], 'Respond with a single JSON object');
    });
});

test('OllamaClient completeJson throws on non-JSON content', function () {
    Http::fake([
        '*/api/chat' => Http::response([
            'message' => ['content' => 'not json'],
        ], 200),
    ]);

    $client = new OllamaClient('http://localhost:11434', 'llama3.2', 10);

    $client->completeJson('system', 'user');
})->throws(AiClientException::class, 'Ollama API returned invalid JSON');
