<?php

use App\Exceptions\AiClientException;
use App\Modules\Ai\Application\Agent\Data\AgentToolDefinition;
use App\Modules\Ai\Infrastructure\OpenAiClient;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('chat returns final content when the model does not request tools', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [
                ['message' => ['content' => 'All done.', 'tool_calls' => null]],
            ],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
        ], 200),
    ]);

    $client = new OpenAiClient('test-key', 10);
    $response = $client->chat([['role' => 'user', 'content' => 'hi']], []);

    expect($response->content)->toBe('All done.')
        ->and($response->hasToolCalls())->toBeFalse()
        ->and($response->promptTokens)->toBe(10)
        ->and($response->completionTokens)->toBe(5);
});

test('chat parses tool calls with decoded arguments', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [
                ['message' => [
                    'content' => null,
                    'tool_calls' => [
                        [
                            'id' => 'call_1',
                            'type' => 'function',
                            'function' => [
                                'name' => 'create_content',
                                'arguments' => json_encode(['title' => 'Test lesson', 'type' => 'book']),
                            ],
                        ],
                    ],
                ]],
            ],
        ], 200),
    ]);

    $client = new OpenAiClient('test-key', 10);
    $definition = new AgentToolDefinition('create_content', 'Creates content', ['type' => 'object', 'properties' => []], AgentToolDefinition::SIDE_EFFECT_DRAFT_ONLY);
    $response = $client->chat([['role' => 'user', 'content' => 'make a lesson']], [$definition]);

    expect($response->hasToolCalls())->toBeTrue()
        ->and($response->toolCalls)->toHaveCount(1)
        ->and($response->toolCalls[0]->id)->toBe('call_1')
        ->and($response->toolCalls[0]->name)->toBe('create_content')
        ->and($response->toolCalls[0]->arguments)->toBe(['title' => 'Test lesson', 'type' => 'book']);

    Http::assertSent(function ($request) {
        return ($request->data()['tools'][0]['function']['name'] ?? null) === 'create_content';
    });
});

test('chat throws when API key is empty', function () {
    $client = new OpenAiClient('', 10);

    $client->chat([['role' => 'user', 'content' => 'hi']], []);
})->throws(AiClientException::class, 'OpenAI API key is not configured');
