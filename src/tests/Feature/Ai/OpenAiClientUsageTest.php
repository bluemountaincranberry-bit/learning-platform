<?php

use App\Modules\Ai\Infrastructure\OpenAiClient;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('lastUsage() is null before any call has been made', function () {
    $client = new OpenAiClient('test-key', 10);

    expect($client->lastUsage())->toBeNull();
});

test('complete() captures usage from the response', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'Hello.']]],
            'usage' => ['prompt_tokens' => 12, 'completion_tokens' => 4],
        ], 200),
    ]);

    $client = new OpenAiClient('test-key', 10);
    $client->complete('system', 'user');

    expect($client->lastUsage()->promptTokens)->toBe(12)
        ->and($client->lastUsage()->completionTokens)->toBe(4);
});

test('completeJson() captures usage from the response', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => '{"a":1}']]],
            'usage' => ['prompt_tokens' => 30, 'completion_tokens' => 8],
        ], 200),
    ]);

    $client = new OpenAiClient('test-key', 10);
    $client->completeJson('system', 'user');

    expect($client->lastUsage()->promptTokens)->toBe(30)
        ->and($client->lastUsage()->completionTokens)->toBe(8);
});

test('lastUsage() is null when the response has no usage block', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'Hello.']]],
        ], 200),
    ]);

    $client = new OpenAiClient('test-key', 10);
    $client->complete('system', 'user');

    expect($client->lastUsage())->toBeNull();
});

test('lastModel() is null before any call has been made', function () {
    $client = new OpenAiClient('test-key', 10);

    expect($client->lastModel())->toBeNull();
});

test('complete() without a model override defaults to gpt-4o-mini, and lastModel() reflects it', function () {
    Http::fake([
        'api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => 'Hi.']]]], 200),
    ]);

    $client = new OpenAiClient('test-key', 10);
    $client->complete('system', 'user');

    Http::assertSent(fn ($request) => $request['model'] === 'gpt-4o-mini');
    expect($client->lastModel())->toBe('gpt-4o-mini');
});

test('complete() with a model override sends and records that model instead of the default', function () {
    Http::fake([
        'api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => 'Hi.']]]], 200),
    ]);

    $client = new OpenAiClient('test-key', 10);
    $client->complete('system', 'user', 'gpt-4o');

    Http::assertSent(fn ($request) => $request['model'] === 'gpt-4o');
    expect($client->lastModel())->toBe('gpt-4o');
});

test('completeJson() with a model override sends and records that model', function () {
    Http::fake([
        'api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => '{}']]]], 200),
    ]);

    $client = new OpenAiClient('test-key', 10);
    $client->completeJson('system', 'user', [], 'gpt-4o');

    Http::assertSent(fn ($request) => $request['model'] === 'gpt-4o');
    expect($client->lastModel())->toBe('gpt-4o');
});

test('a later call overwrites lastUsage() from an earlier call', function () {
    Http::fake([
        'api.openai.com/*' => Http::sequence()
            ->push(['choices' => [['message' => ['content' => 'a']]], 'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1]])
            ->push(['choices' => [['message' => ['content' => 'b']]], 'usage' => ['prompt_tokens' => 99, 'completion_tokens' => 99]]),
    ]);

    $client = new OpenAiClient('test-key', 10);
    $client->complete('system', 'user');
    $client->complete('system', 'user');

    expect($client->lastUsage()->promptTokens)->toBe(99);
});
