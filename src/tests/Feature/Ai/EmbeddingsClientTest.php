<?php

use App\Exceptions\AiClientException;
use App\Modules\Ai\Infrastructure\OllamaEmbeddingsClient;
use App\Modules\Ai\Infrastructure\OpenAiEmbeddingsClient;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('OpenAiEmbeddingsClient returns vector of floats on success', function () {
    $vector = array_fill(0, 1536, 0.002);
    Http::fake([
        'api.openai.com/*' => Http::response([
            'data' => [
                ['embedding' => $vector],
            ],
        ], 200),
    ]);

    $client = new OpenAiEmbeddingsClient('test-key', 'text-embedding-3-small', 10);
    $result = $client->embed('hello world');

    expect($result)->toBeArray()
        ->and($result)->toHaveCount(1536)
        ->and($result[0])->toBeFloat();
});

test('OpenAiEmbeddingsClient throws when API key is empty', function () {
    $client = new OpenAiEmbeddingsClient('', 'text-embedding-3-small', 10);

    $client->embed('hello');
})->throws(AiClientException::class, 'OpenAI API key is not configured');

test('OpenAiEmbeddingsClient throws on API error response', function () {
    Http::fake([
        'api.openai.com/*' => Http::response(['error' => ['message' => 'Rate limit']], 429),
    ]);

    $client = new OpenAiEmbeddingsClient('test-key', 'text-embedding-3-small', 10);

    $client->embed('hello');
})->throws(AiClientException::class, 'OpenAI embeddings API error');

test('OpenAiEmbeddingsClient throws on invalid response shape', function () {
    Http::fake([
        'api.openai.com/*' => Http::response(['data' => []], 200),
    ]);

    $client = new OpenAiEmbeddingsClient('test-key', 'text-embedding-3-small', 10);

    $client->embed('hello');
})->throws(AiClientException::class, 'OpenAI embeddings API returned invalid response');

test('OllamaEmbeddingsClient maps batched vectors and preserves input order', function () {
    Http::fake([
        'ollama.test/api/embed' => Http::response([
            'embeddings' => [[1, 2], [3, 4]],
        ]),
    ]);

    $client = new OllamaEmbeddingsClient('http://ollama.test', 'nomic-embed-text', 10);

    expect($client->embedBatch(['one', 'two']))->toBe([[1.0, 2.0], [3.0, 4.0]]);
});
