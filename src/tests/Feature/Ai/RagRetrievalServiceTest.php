<?php

use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Domain\Models\GrammarTopic;
use App\Modules\Ai\Application\RagIndexingService;
use App\Modules\Ai\Application\RagRetrievalService;
use App\Modules\Ai\Infrastructure\ElasticsearchClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->ragIndex = 'rag_corpus_test_'.str_replace('.', '', uniqid('', true));
    config([
        'elasticsearch.enabled' => true,
        'elasticsearch.rag.index' => $this->ragIndex,
        'elasticsearch.rag.min_score' => 0.6,
        'ai.openai.api_key' => 'test-key',
    ]);
});

afterEach(function () {
    app(ElasticsearchClient::class)->deleteIndex($this->ragIndex);
});

/**
 * Task 2.4 acceptance test: on a small fixture corpus (a relevant grammar
 * rule and a semantically unrelated one), an irrelevant document does not
 * end up in the top-K results. Embeddings are faked with orthogonal unit
 * vectors so the outcome is deterministic (real OpenAI embeddings aren't
 * reachable from this sandbox — see EmbeddingsClientInterface convention
 * used elsewhere, e.g. ComputeGrammarRuleEmbeddingsJobTest): a query vector
 * identical to the relevant document's vector (cosine similarity 1, ES
 * normalized score 1.0) versus an orthogonal one (cosine 0, normalized
 * score 0.5) — config('elasticsearch.rag.min_score') = 0.6 sits strictly
 * between the two, so this exercises the real filtering logic, not a
 * tautology.
 */
test('retrieve excludes a semantically irrelevant document from the top-K', function () {
    $relevantVector = array_fill(0, 1536, 0.0);
    $relevantVector[0] = 1.0;
    $irrelevantVector = array_fill(0, 1536, 0.0);
    $irrelevantVector[1] = 1.0;

    Http::fake([
        'api.openai.com/*' => Http::sequence()
            ->push(['data' => [['embedding' => $relevantVector]]])   // indexing the relevant rule
            ->push(['data' => [['embedding' => $irrelevantVector]]]) // indexing the irrelevant rule
            ->push(['data' => [['embedding' => $relevantVector]]]),  // the query itself
    ]);

    $topicA = GrammarTopic::query()->create(['slug' => 'rel-topic-'.uniqid(), 'language' => 'en', 'name' => 'A', 'status' => 'active']);
    $relevantRule = GrammarRule::query()->create([
        'topic_id' => $topicA->id, 'slug' => 'rel-rule-'.uniqid(), 'language' => 'en',
        'title' => 'Present Perfect', 'status' => GrammarRule::STATUS_PUBLISHED,
        'summary' => 'Used for past actions with present relevance.', 'body' => 'have/has + past participle',
    ]);
    $topicB = GrammarTopic::query()->create(['slug' => 'irr-topic-'.uniqid(), 'language' => 'en', 'name' => 'B', 'status' => 'active']);
    $irrelevantRule = GrammarRule::query()->create([
        'topic_id' => $topicB->id, 'slug' => 'irr-rule-'.uniqid(), 'language' => 'en',
        'title' => 'Definite Articles', 'status' => GrammarRule::STATUS_PUBLISHED,
        'summary' => 'Unrelated construction.', 'body' => 'the + noun',
    ]);

    $indexing = app(RagIndexingService::class);
    $indexing->indexGrammarRules([$relevantRule->id]);
    $indexing->indexGrammarRules([$irrelevantRule->id]);

    $results = app(RagRetrievalService::class)->retrieve('present perfect', docType: 'grammar_rule', topK: 5);

    $sourceIds = collect($results)->pluck('source_id')->all();
    expect($sourceIds)->toBe([$relevantRule->id])
        ->and($sourceIds)->not->toContain($irrelevantRule->id);
});

test('retrieve returns an empty array when Elasticsearch is disabled', function () {
    config(['elasticsearch.enabled' => false]);

    $results = app(RagRetrievalService::class)->retrieve('anything', docType: 'grammar_rule');

    expect($results)->toBe([]);
});

test('retrieve returns an empty array when the index does not exist yet', function () {
    Http::fake([
        'api.openai.com/*' => Http::response(['data' => [['embedding' => array_fill(0, 1536, 0.01)]]], 200),
    ]);

    $results = app(RagRetrievalService::class)->retrieve('anything', docType: 'grammar_rule');

    expect($results)->toBe([]);
});
