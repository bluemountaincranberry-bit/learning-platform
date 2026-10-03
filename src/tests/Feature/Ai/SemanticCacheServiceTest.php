<?php

use App\Modules\Ai\Domain\Models\AiSemanticCacheEntry;
use App\Modules\Ai\Application\SemanticCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * Task 5.4: real embeddings-shaped fakes (same convention as
 * RagIndexingServiceTest — no network reachable to OpenAI in this sandbox
 * for arbitrary keys, but the HTTP call itself is exercised for real
 * through Http::fake, not bypassed).
 */
function fakeEmbeddingFor(array $vectors): void
{
    Http::fake([
        'api.openai.com/v1/embeddings' => Http::sequence(
            array_map(fn (array $v) => Http::response(['data' => [['embedding' => $v]]], 200), $vectors)
        ),
    ]);
    config(['ai.openai.api_key' => 'test-key']);
}

test('remember calls compute on a cold cache and stores the result', function () {
    fakeEmbeddingFor([array_fill(0, 1536, 0.1)]);

    $calls = 0;
    $result = app(SemanticCacheService::class)->remember('test_scope', 'what is present perfect', function () use (&$calls) {
        $calls++;

        return 'Present perfect describes past actions with present relevance.';
    });

    expect($result)->toBe('Present perfect describes past actions with present relevance.')
        ->and($calls)->toBe(1)
        ->and(AiSemanticCacheEntry::query()->where('scope', 'test_scope')->count())->toBe(1);
});

test('remember returns the cached answer for a near-duplicate question without calling compute again', function () {
    // Same vector both times simulates "worded differently, means the
    // same thing" — cosine similarity of an identical vector is 1.0,
    // comfortably above the configured threshold.
    fakeEmbeddingFor([array_fill(0, 1536, 0.1), array_fill(0, 1536, 0.1)]);

    $cache = app(SemanticCacheService::class);
    $cache->remember('test_scope', 'what is present perfect', fn () => 'Original answer.');

    $calls = 0;
    $result = $cache->remember('test_scope', 'can you explain present perfect to me', function () use (&$calls) {
        $calls++;

        return 'Should not be used.';
    });

    expect($result)->toBe('Original answer.')
        ->and($calls)->toBe(0)
        ->and(AiSemanticCacheEntry::query()->where('scope', 'test_scope')->count())->toBe(1);
});

test('remember does not match a dissimilar question and stores it as a second entry', function () {
    $vectorA = array_fill(0, 1536, 0.0);
    $vectorA[0] = 1.0;
    $vectorB = array_fill(0, 1536, 0.0);
    $vectorB[1] = 1.0;
    // Orthogonal vectors -> cosine similarity 0.0, well under the threshold.
    fakeEmbeddingFor([$vectorA, $vectorB]);

    $cache = app(SemanticCacheService::class);
    $cache->remember('test_scope', 'what is present perfect', fn () => 'Answer A.');

    $calls = 0;
    $result = $cache->remember('test_scope', 'how do I conjugate irregular verbs', function () use (&$calls) {
        $calls++;

        return 'Answer B.';
    });

    expect($result)->toBe('Answer B.')
        ->and($calls)->toBe(1)
        ->and(AiSemanticCacheEntry::query()->where('scope', 'test_scope')->count())->toBe(2);
});

test('remember never matches across scopes', function () {
    fakeEmbeddingFor([array_fill(0, 1536, 0.1), array_fill(0, 1536, 0.1)]);

    $cache = app(SemanticCacheService::class);
    $cache->remember('scope_a', 'what is present perfect', fn () => 'Answer for scope A.');

    $calls = 0;
    $result = $cache->remember('scope_b', 'what is present perfect', function () use (&$calls) {
        $calls++;

        return 'Answer for scope B.';
    });

    expect($result)->toBe('Answer for scope B.')
        ->and($calls)->toBe(1);
});

test('remember is a pass-through when the semantic cache is disabled', function () {
    config(['ai.semantic_cache.enabled' => false]);
    Http::fake(); // no embeddings call should happen at all

    $result = app(SemanticCacheService::class)->remember('test_scope', 'anything', fn () => 'Direct answer.');

    expect($result)->toBe('Direct answer.')
        ->and(AiSemanticCacheEntry::query()->count())->toBe(0);
});

test('remember falls back to compute (and does not throw) when the embeddings call fails', function () {
    Http::fake([
        'api.openai.com/v1/embeddings' => Http::response(['error' => ['message' => 'boom']], 500),
    ]);
    config(['ai.openai.api_key' => 'test-key']);

    $calls = 0;
    $result = app(SemanticCacheService::class)->remember('test_scope', 'anything', function () use (&$calls) {
        $calls++;

        return 'Fallback answer.';
    });

    expect($result)->toBe('Fallback answer.')
        ->and($calls)->toBe(1)
        // Nothing gets stored either — the vector that would key the
        // write never came back.
        ->and(AiSemanticCacheEntry::query()->count())->toBe(0);
});

test('remember ignores expired entries', function () {
    fakeEmbeddingFor([array_fill(0, 1536, 0.1), array_fill(0, 1536, 0.1)]);
    config(['ai.semantic_cache.ttl_seconds' => 60]);

    $cache = app(SemanticCacheService::class);
    $cache->remember('test_scope', 'what is present perfect', fn () => 'Old answer.');

    $this->travel(2)->minutes();

    $calls = 0;
    $result = $cache->remember('test_scope', 'what is present perfect', function () use (&$calls) {
        $calls++;

        return 'Fresh answer.';
    });

    expect($result)->toBe('Fresh answer.')
        ->and($calls)->toBe(1);
});
