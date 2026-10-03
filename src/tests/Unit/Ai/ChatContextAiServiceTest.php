<?php

use App\Modules\Ai\Application\Agent\Tracing\NullSpanRecorder;
use App\Modules\Ai\Application\Agent\Tracing\TracedLlmCall;
use App\Modules\Ai\Application\Data\RenderedPrompt;
use App\Contracts\Ai\AiClientInterface;
use App\Contracts\Ai\PromptRegistryInterface;
use App\Exceptions\AiClientException;
use App\Modules\Ai\Application\ChatContextAiService;
use App\Modules\Ai\Application\SemanticCacheService;

function chatContextTracedCall(): TracedLlmCall
{
    return new TracedLlmCall(new NullSpanRecorder);
}

/**
 * Same "no active override" passthrough as AiExplainLexemeServiceTest's
 * PassthroughPromptRegistry — named differently to avoid a class
 * redeclaration collision when both files load in the same test run.
 */
final class ChatContextPassthroughPromptRegistry implements PromptRegistryInterface
{
    public function resolve(string $key, array $variables, \Closure $default): RenderedPrompt
    {
        $fallback = $default();

        return new RenderedPrompt($fallback['system'], $fallback['user'], $fallback['model'] ?? null, false);
    }
}

/**
 * `SemanticCacheService` (task 5.4) is fully doubled here, not the real
 * class — a real instance would call `config()`/query the DB from
 * `remember()`, which this pure Mockery-based Unit suite (no Laravel app
 * bootstrapped, see AiExplainLexemeServiceTest's equivalent avoidance of
 * config()-touching paths) cannot do. `passthroughCache()` mimics
 * `remember()`'s cache-miss behavior (just call `$compute()`) for the
 * `history === []` case that reaches it.
 */
function passthroughCache(): SemanticCacheService
{
    $cache = Mockery::mock(SemanticCacheService::class);
    $cache->shouldReceive('remember')->andReturnUsing(fn (string $scope, string $query, callable $compute) => $compute());

    return $cache;
}

test('reply builds prompt with system and history then returns trimmed response', function () {
    $client = Mockery::mock(AiClientInterface::class);
    $client->shouldReceive('complete')
        ->once()
        ->with(
            Mockery::on(fn ($s) => str_contains($s, 'language learning assistant') && str_contains($s, 'concise')),
            Mockery::on(fn ($u) => str_contains($u, 'User: hello')
                && str_contains($u, 'Assistant: Hi!')
                && str_contains($u, 'User: bye')
                && str_contains($u, 'Assistant:')
            ),
            null
        )
        ->andReturn('  Goodbye.  ');

    // Non-empty history — the semantic cache is never consulted for this
    // call (see ChatContextAiService::reply()'s docblock), so a bare mock
    // with no expectations is enough.
    $service = new ChatContextAiService($client, Mockery::mock(SemanticCacheService::class), chatContextTracedCall(), new ChatContextPassthroughPromptRegistry);
    $history = [
        ['role' => 'user', 'content' => 'hello'],
        ['role' => 'assistant', 'content' => 'Hi!'],
        ['role' => 'user', 'content' => 'bye'],
    ];
    $result = $service->reply($history, 'bye');

    expect($result)->toBe('Goodbye.');
});

test('reply limits history to last 20 messages', function () {
    $client = Mockery::mock(AiClientInterface::class);
    $client->shouldReceive('complete')
        ->once()
        ->with(
            Mockery::type('string'),
            Mockery::on(function ($u) {
                $userLines = array_filter(explode("\n", $u), fn ($l) => str_starts_with($l, 'User:'));
                return count($userLines) <= 11; // 10 history user + 1 new
            }),
            null
        )
        ->andReturn('OK');

    $history = [];
    for ($i = 0; $i < 30; $i++) {
        $history[] = ['role' => 'user', 'content' => "msg{$i}"];
        $history[] = ['role' => 'assistant', 'content' => "r{$i}"];
    }
    $service = new ChatContextAiService($client, Mockery::mock(SemanticCacheService::class), chatContextTracedCall(), new ChatContextPassthroughPromptRegistry);
    $service->reply($history, 'last');
});

test('reply propagates AiClientException', function () {
    $client = Mockery::mock(AiClientInterface::class);
    $client->shouldReceive('complete')->once()->andThrow(new AiClientException('Timeout'));

    $service = new ChatContextAiService($client, passthroughCache(), chatContextTracedCall(), new ChatContextPassthroughPromptRegistry);
    $service->reply([], 'hello');
})->throws(AiClientException::class, 'Timeout');

test('reply with no history goes through the semantic cache', function () {
    $client = Mockery::mock(AiClientInterface::class);
    $client->shouldReceive('complete')->once()->andReturn('Hi there!');

    $service = new ChatContextAiService($client, passthroughCache(), chatContextTracedCall(), new ChatContextPassthroughPromptRegistry);
    $result = $service->reply([], 'hello');

    expect($result)->toBe('Hi there!');
});
