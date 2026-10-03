<?php

use App\Modules\Ai\Application\Agent\Tracing\NullSpanRecorder;
use App\Modules\Ai\Application\Agent\Tracing\TracedLlmCall;
use App\Contracts\Ai\AiClientInterface;
use App\Contracts\Ai\AiJsonClient;
use App\Contracts\Ai\PromptRegistryInterface;
use App\Modules\Ai\Application\Data\RenderedPrompt;
use App\Exceptions\AiClientException;
use App\Modules\Ai\Application\AiExplainLexemeService;

function explainLexemeTracedCall(): TracedLlmCall
{
    return new TracedLlmCall(new NullSpanRecorder);
}

/**
 * Simulates "no active PromptRegistryInterface override configured" —
 * always falls through to the caller's own $default() closure, so these
 * tests keep asserting on AiExplainLexemeService's own hardcoded prompt
 * text, unchanged by the registry's existence. PromptRegistryServiceTest
 * covers the actual override/fallback resolution logic.
 */
final class PassthroughPromptRegistry implements PromptRegistryInterface
{
    public function resolve(string $key, array $variables, \Closure $default): RenderedPrompt
    {
        $fallback = $default();

        return new RenderedPrompt($fallback['system'], $fallback['user'], $fallback['model'] ?? null, false);
    }
}

test('explain builds prompts and returns trimmed client response', function () {
    $client = Mockery::mock(AiClientInterface::class);
    $client->shouldReceive('complete')
        ->once()
        ->with(
            Mockery::on(fn ($s) => str_contains($s, 'language tutor') && str_contains($s, 'short')),
            Mockery::on(fn ($u) => str_contains($u, 'Hello') && str_contains($u, 'English')),
            null
        )
        ->andReturn('  "Hello" is a greeting.  ');

    $service = new AiExplainLexemeService($client, Mockery::mock(AiJsonClient::class), new PassthroughPromptRegistry, explainLexemeTracedCall());
    $result = $service->explain('Hello', 'English');

    expect($result)->toBe('"Hello" is a greeting.');
});

test('explain without language omits language in user prompt', function () {
    $client = Mockery::mock(AiClientInterface::class);
    $client->shouldReceive('complete')
        ->once()
        ->with(
            Mockery::type('string'),
            Mockery::on(fn ($u) => str_contains($u, 'foo') && ! str_contains($u, 'Target language')),
            null
        )
        ->andReturn('Explanation for foo.');

    $service = new AiExplainLexemeService($client, Mockery::mock(AiJsonClient::class), new PassthroughPromptRegistry, explainLexemeTracedCall());
    $result = $service->explain('foo', null);

    expect($result)->toBe('Explanation for foo.');
});

test('explain propagates AiClientException', function () {
    $client = Mockery::mock(AiClientInterface::class);
    $client->shouldReceive('complete')->once()->andThrow(new AiClientException('Timeout'));

    $service = new AiExplainLexemeService($client, Mockery::mock(AiJsonClient::class), new PassthroughPromptRegistry, explainLexemeTracedCall());
    $service->explain('word', null);
})->throws(AiClientException::class, 'Timeout');

test('suggestLevel builds CEFR prompt and returns trimmed response', function () {
    $client = Mockery::mock(AiClientInterface::class);
    $client->shouldReceive('complete')
        ->once()
        ->with(
            Mockery::on(fn ($s) => str_contains($s, 'CEFR') && str_contains($s, 'A1')),
            Mockery::on(fn ($u) => str_contains($u, 'run')),
            null
        )
        ->andReturn('  B1  ');

    $service = new AiExplainLexemeService($client, Mockery::mock(AiJsonClient::class), new PassthroughPromptRegistry, explainLexemeTracedCall());
    $result = $service->suggestLevel('run');

    expect($result)->toBe('B1');
});

test('suggestLevel propagates AiClientException', function () {
    $client = Mockery::mock(AiClientInterface::class);
    $client->shouldReceive('complete')->once()->andThrow(new AiClientException('API error'));

    $service = new AiExplainLexemeService($client, Mockery::mock(AiJsonClient::class), new PassthroughPromptRegistry, explainLexemeTracedCall());
    $service->suggestLevel('word');
})->throws(AiClientException::class, 'API error');

test('suggestMetadata builds a combined CEFR + part-of-speech prompt and returns both trimmed', function () {
    $client = Mockery::mock(AiClientInterface::class);
    $jsonClient = Mockery::mock(AiJsonClient::class);
    $jsonClient->shouldReceive('completeJson')
        ->once()
        ->with(
            Mockery::on(fn ($s) => str_contains($s, 'CEFR level') && str_contains($s, 'part of speech')),
            Mockery::on(fn ($u) => str_contains($u, 'run')),
            Mockery::type('array'),
            null
        )
        ->andReturn(['level' => '  B1  ', 'part_of_speech' => '  verb  ']);

    $service = new AiExplainLexemeService($client, $jsonClient, new PassthroughPromptRegistry, explainLexemeTracedCall());
    $result = $service->suggestMetadata('run');

    expect($result)->toBe(['level' => 'B1', 'part_of_speech' => 'verb']);
});

test('suggestMetadata returns nulls for missing or non-string fields', function () {
    $client = Mockery::mock(AiClientInterface::class);
    $jsonClient = Mockery::mock(AiJsonClient::class);
    $jsonClient->shouldReceive('completeJson')->once()->andReturn(['level' => null]);

    $service = new AiExplainLexemeService($client, $jsonClient, new PassthroughPromptRegistry, explainLexemeTracedCall());
    $result = $service->suggestMetadata('word');

    expect($result)->toBe(['level' => null, 'part_of_speech' => null]);
});

test('suggestMetadata propagates AiClientException', function () {
    $client = Mockery::mock(AiClientInterface::class);
    $jsonClient = Mockery::mock(AiJsonClient::class);
    $jsonClient->shouldReceive('completeJson')->once()->andThrow(new AiClientException('API error'));

    $service = new AiExplainLexemeService($client, $jsonClient, new PassthroughPromptRegistry, explainLexemeTracedCall());
    $service->suggestMetadata('word');
})->throws(AiClientException::class, 'API error');

test('analyzeForManualAdd builds a single-item prompt and parses the full candidate-shaped response (task 10.6)', function () {
    $client = Mockery::mock(AiClientInterface::class);
    $jsonClient = Mockery::mock(AiJsonClient::class);
    $jsonClient->shouldReceive('completeJson')
        ->once()
        ->with(
            Mockery::on(fn ($s) => str_contains($s, 'lexicographer') && str_contains($s, 'part_of_speech')),
            Mockery::on(fn ($u) => str_contains($u, 'ran') && str_contains($u, 'He ran across the street.')),
            Mockery::type('array'),
            null
        )
        ->andReturn([
            'lemma' => 'run', 'part_of_speech' => 'verb', 'sense' => 'move quickly on foot',
            'translation' => 'побежал', 'level' => 'A1', 'example_translation' => 'Он перебежал улицу.',
            'grammar' => ['tense' => 'past', 'is_irregular' => true],
        ]);

    $service = new AiExplainLexemeService($client, $jsonClient, new PassthroughPromptRegistry, explainLexemeTracedCall());
    $result = $service->analyzeForManualAdd('ran', 'He ran across the street.', 'en', 'ru');

    expect($result)->toBe([
        'lemma' => 'run',
        'part_of_speech' => 'verb',
        'sense' => 'move quickly on foot',
        'translation' => 'побежал',
        'level' => 'A1',
        'grammar_features' => ['tense' => 'past', 'is_irregular' => true],
        'example' => 'He ran across the street.',
        'example_translation' => 'Он перебежал улицу.',
    ]);
});

test('analyzeForManualAdd falls back to the selected text as lemma and drops invalid level/part_of_speech', function () {
    $client = Mockery::mock(AiClientInterface::class);
    $jsonClient = Mockery::mock(AiJsonClient::class);
    $jsonClient->shouldReceive('completeJson')->once()->andReturn([
        'translation' => 'вездесущий', 'level' => 'not-a-level', 'part_of_speech' => 'not-a-real-pos',
    ]);

    $service = new AiExplainLexemeService($client, $jsonClient, new PassthroughPromptRegistry, explainLexemeTracedCall());
    $result = $service->analyzeForManualAdd('ubiquitous', 'It is ubiquitous.', 'en', 'ru');

    expect($result['lemma'])->toBe('ubiquitous')
        ->and($result['level'])->toBeNull()
        ->and($result['part_of_speech'])->toBeNull()
        ->and($result['sense'])->toBeNull()
        ->and($result['grammar_features'])->toBeNull();
});

test('analyzeForManualAdd propagates AiClientException', function () {
    $client = Mockery::mock(AiClientInterface::class);
    $jsonClient = Mockery::mock(AiJsonClient::class);
    $jsonClient->shouldReceive('completeJson')->once()->andThrow(new AiClientException('API error'));

    $service = new AiExplainLexemeService($client, $jsonClient, new PassthroughPromptRegistry, explainLexemeTracedCall());
    $service->analyzeForManualAdd('word', 'A sentence.', 'en', 'ru');
})->throws(AiClientException::class, 'API error');
