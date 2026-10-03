<?php

use App\Exceptions\AiClientException;
use App\Modules\Ai\Application\Agent\Data\AgentToolDefinition;
use App\Modules\Ai\Infrastructure\OpenAiClient;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

function sseBody(array $lines): string
{
    return implode('', array_map(fn ($chunk) => 'data: '.json_encode($chunk)."\n\n", $lines))."data: [DONE]\n\n";
}

test('chatStream forwards content deltas and assembles the final response', function () {
    Http::fake([
        'api.openai.com/*' => Http::response(sseBody([
            ['choices' => [['delta' => ['content' => 'Hel']]]],
            ['choices' => [['delta' => ['content' => 'lo']]]],
            ['choices' => [['delta' => []]], 'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 2]],
        ]), 200, ['Content-Type' => 'text/event-stream']),
    ]);

    $client = new OpenAiClient('test-key', 10);
    $deltas = [];

    $response = $client->chatStream([['role' => 'user', 'content' => 'hi']], [], function (string $delta) use (&$deltas) {
        $deltas[] = $delta;
    });

    expect($deltas)->toBe(['Hel', 'lo'])
        ->and($response->content)->toBe('Hello')
        ->and($response->hasToolCalls())->toBeFalse()
        ->and($response->promptTokens)->toBe(10)
        ->and($response->completionTokens)->toBe(2);

    Http::assertSent(fn ($request) => $request['stream'] === true);
});

test('chatStream accumulates fragmented tool_calls deltas by index and fires no content deltas', function () {
    function toolCallDeltaChunk(array $toolCallDelta): array
    {
        return ['choices' => [['delta' => ['tool_calls' => [$toolCallDelta]]]]];
    }

    Http::fake([
        'api.openai.com/*' => Http::response(sseBody([
            toolCallDeltaChunk(['index' => 0, 'id' => 'call_1', 'function' => ['name' => 'get_', 'arguments' => '']]),
            toolCallDeltaChunk(['index' => 0, 'function' => ['name' => 'user_level', 'arguments' => '{"lang']]),
            toolCallDeltaChunk(['index' => 0, 'function' => ['arguments' => '":"en"}']]),
        ]), 200, ['Content-Type' => 'text/event-stream']),
    ]);

    $client = new OpenAiClient('test-key', 10);
    $deltas = [];

    $response = $client->chatStream(
        [['role' => 'user', 'content' => 'hi']],
        [new AgentToolDefinition('get_user_level', 'desc', ['type' => 'object', 'properties' => []], AgentToolDefinition::SIDE_EFFECT_READ_ONLY)],
        function (string $delta) use (&$deltas) { $deltas[] = $delta; }
    );

    expect($deltas)->toBe([])
        ->and($response->hasToolCalls())->toBeTrue()
        ->and($response->toolCalls[0]->id)->toBe('call_1')
        ->and($response->toolCalls[0]->name)->toBe('get_user_level')
        ->and($response->toolCalls[0]->arguments)->toBe(['lang' => 'en']);
});

test('chatStream throws when the API key is empty', function () {
    $client = new OpenAiClient('', 10);

    $client->chatStream([], [], function () {});
})->throws(AiClientException::class, 'OpenAI API key is not configured');

test('chatStream throws AiClientException on a failed response', function () {
    Http::fake([
        'api.openai.com/*' => Http::response(['error' => ['message' => 'boom']], 500),
    ]);

    $client = new OpenAiClient('test-key', 10);

    $client->chatStream([], [], function () {});
})->throws(AiClientException::class);
