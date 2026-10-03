<?php

use App\Modules\Ai\Interfaces\Jobs\ComputeGrammarRuleEmbeddingsJob;
use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Ai\Domain\Models\GrammarRuleEmbedding;
use App\Modules\Content\Domain\Models\GrammarTopic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.openai.com/*' => Http::response([
            'data' => [
                ['embedding' => array_fill(0, 1536, 0.01)],
            ],
        ], 200),
    ]);
    config(['ai.openai.api_key' => 'test-key', 'ai.enabled' => true]);
});

function makeGrammarRuleForEmbedding(string $slug): GrammarRule
{
    $topic = GrammarTopic::query()->create([
        'slug' => $slug.'-topic', 'language' => 'en', 'name' => 'Topic '.$slug, 'status' => 'active',
    ]);

    return GrammarRule::query()->create([
        'topic_id' => $topic->id,
        'slug' => $slug,
        'language' => 'en',
        'title' => 'Rule '.$slug,
        'status' => GrammarRule::STATUS_PUBLISHED,
        'summary' => 'Summary for '.$slug,
    ]);
}

test('compute grammar rule embeddings job stores vectors for given rule ids', function () {
    $rule = makeGrammarRuleForEmbedding('rule-a');

    (new ComputeGrammarRuleEmbeddingsJob([$rule->id]))->handle(app(\App\Contracts\Ai\EmbeddingsClientInterface::class), app(\App\Modules\Content\Application\Contracts\EmbeddingSourceReaderInterface::class));

    $row = GrammarRuleEmbedding::query()->where('grammar_rule_id', $rule->id)->first();
    expect($row)->not->toBeNull()
        ->and($row->embedding)->toHaveCount(1536)
        ->and($row->model_version)->toBe('text-embedding-3-small');
});

test('compute grammar rule embeddings job is idempotent', function () {
    $rule = makeGrammarRuleForEmbedding('rule-b');

    $embeddings = app(\App\Contracts\Ai\EmbeddingsClientInterface::class);
    (new ComputeGrammarRuleEmbeddingsJob([$rule->id]))->handle($embeddings, app(\App\Modules\Content\Application\Contracts\EmbeddingSourceReaderInterface::class));
    (new ComputeGrammarRuleEmbeddingsJob([$rule->id]))->handle($embeddings, app(\App\Modules\Content\Application\Contracts\EmbeddingSourceReaderInterface::class));

    expect(GrammarRuleEmbedding::query()->where('grammar_rule_id', $rule->id)->count())->toBe(1);
});

test('job is a no-op when the general AI feature flag is disabled', function () {
    config(['ai.enabled' => false]);
    Http::preventStrayRequests();
    $rule = makeGrammarRuleForEmbedding('rule-c');

    (new ComputeGrammarRuleEmbeddingsJob([$rule->id]))->handle(app(\App\Contracts\Ai\EmbeddingsClientInterface::class), app(\App\Modules\Content\Application\Contracts\EmbeddingSourceReaderInterface::class));

    expect(GrammarRuleEmbedding::query()->where('grammar_rule_id', $rule->id)->exists())->toBeFalse();
});
