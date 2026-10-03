<?php

use App\Exceptions\AiClientException;
use App\Modules\Ai\Infrastructure\OllamaClient;
use App\Modules\Ai\Infrastructure\OpenAiClient;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('OpenAiClient returns completion text on success', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [
                ['message' => ['content' => 'This word means something.']],
            ],
        ], 200),
    ]);

    $client = new OpenAiClient('test-key', 10);
    $result = $client->complete('You are helpful.', 'Explain "foo".');

    expect($result)->toBe('This word means something.');
});

test('OpenAiClient throws when API key is empty', function () {
    $client = new OpenAiClient('', 10);

    $client->complete('You are helpful.', 'Explain "foo".');
})->throws(AiClientException::class, 'OpenAI API key is not configured');

test('OpenAiClient throws on API error response', function () {
    Http::fake([
        'api.openai.com/*' => Http::response(['error' => ['message' => 'Rate limit']], 429),
    ]);

    $client = new OpenAiClient('test-key', 10);

    $client->complete('You are helpful.', 'Explain "foo".');
})->throws(AiClientException::class, 'OpenAI API error');

test('OllamaClient returns completion text on success', function () {
    Http::fake([
        'http://localhost:11434/*' => Http::response([
            'message' => ['content' => 'Ollama says hi.'],
        ], 200),
    ]);

    $client = new OllamaClient('http://localhost:11434', 'llama3.2', 10);
    $result = $client->complete('You are helpful.', 'Say hi.');

    expect($result)->toBe('Ollama says hi.');
});

test('OllamaClient throws on API error response', function () {
    Http::fake([
        'http://localhost:11434/*' => Http::response(null, 503),
    ]);

    $client = new OllamaClient('http://localhost:11434', 'llama3.2', 10);

    $client->complete('You are helpful.', 'Say hi.');
})->throws(AiClientException::class, 'Ollama API error');
