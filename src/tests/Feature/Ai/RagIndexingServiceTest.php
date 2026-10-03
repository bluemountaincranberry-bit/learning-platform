<?php

use App\Console\Commands\IndexRagCorpusCommand;
use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Domain\Models\GrammarTopic;
use App\Modules\Content\Domain\Models\Lexeme;
use App\Modules\Ai\Application\RagIndexingService;
use App\Modules\Ai\Infrastructure\ElasticsearchClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * Task 2.3 acceptance test: "after the command runs, the document is
 * findable by a vector query" — run against the real Elasticsearch
 * instance in this environment (verified reachable via a plain cluster
 * health call before this suite was written), with only the OpenAI
 * embeddings HTTP call faked (same convention as
 * ComputeGrammarRuleEmbeddingsJobTest — no network to OpenAI is reachable
 * in this sandbox anyway).
 */
beforeEach(function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'data' => [
                ['embedding' => array_fill(0, 1536, 0.01)],
            ],
        ], 200),
    ]);
    config(['ai.openai.api_key' => 'test-key']);

    $this->ragIndex = 'rag_corpus_test_'.str_replace('.', '', uniqid('', true));
    config([
        'elasticsearch.enabled' => true,
        'elasticsearch.rag.index' => $this->ragIndex,
    ]);
});

afterEach(function () {
    app(ElasticsearchClient::class)->deleteIndex($this->ragIndex);
});

test('indexing a grammar rule makes it findable by a kNN vector query', function () {
    $topic = GrammarTopic::query()->create(['slug' => 'idx-topic-'.uniqid(), 'language' => 'en', 'name' => 'Idx', 'status' => 'active']);
    $rule = GrammarRule::query()->create([
        'topic_id' => $topic->id, 'slug' => 'idx-rule-'.uniqid(), 'language' => 'en',
        'title' => 'Present Perfect', 'status' => GrammarRule::STATUS_PUBLISHED,
        'summary' => 'Used for past actions with present relevance.', 'body' => 'have/has + past participle',
    ]);

    $indexed = app(RagIndexingService::class)->indexGrammarRules([$rule->id]);
    expect($indexed)->toBe(1);

    $response = app(ElasticsearchClient::class)->search($this->ragIndex, [
        'knn' => [
            'field' => 'embedding',
            'query_vector' => array_fill(0, 1536, 0.01),
            'k' => 1,
            'num_candidates' => 10,
        ],
    ]);

    expect($response['hits']['hits'])->toHaveCount(1)
        ->and($response['hits']['hits'][0]['_source']['doc_type'])->toBe('grammar_rule')
        ->and($response['hits']['hits'][0]['_source']['source_id'])->toBe($rule->id);
});

test('indexing a lexeme example makes it findable by a kNN vector query', function () {
    $lexeme = Lexeme::query()->create(['slug' => 'idx-giveup', 'language' => 'en', 'lemma' => 'give up', 'normalized_lemma' => 'give up', 'status' => Lexeme::STATUS_PUBLISHED]);
    $example = $lexeme->examples()->create(['language' => 'en', 'example' => 'Never give up.', 'translation' => 'Никогда не сдавайся.', 'is_primary' => true, 'sort_order' => 1]);

    $indexed = app(RagIndexingService::class)->indexLexemeExamples([$example->id]);
    expect($indexed)->toBe(1);

    $response = app(ElasticsearchClient::class)->search($this->ragIndex, [
        'knn' => [
            'field' => 'embedding',
            'query_vector' => array_fill(0, 1536, 0.01),
            'k' => 1,
            'num_candidates' => 10,
        ],
    ]);

    expect($response['hits']['hits'])->toHaveCount(1)
        ->and($response['hits']['hits'][0]['_source']['doc_type'])->toBe('lexeme_example')
        ->and($response['hits']['hits'][0]['_source']['source_id'])->toBe($example->id);
});

test('IndexRagCorpusCommand indexes all published grammar rules and lexeme examples', function () {
    $topic = GrammarTopic::query()->create(['slug' => 'cmd-topic-'.uniqid(), 'language' => 'en', 'name' => 'Cmd', 'status' => 'active']);
    GrammarRule::query()->create([
        'topic_id' => $topic->id, 'slug' => 'cmd-rule-'.uniqid(), 'language' => 'en',
        'title' => 'Cmd Rule', 'status' => GrammarRule::STATUS_PUBLISHED, 'summary' => 's', 'body' => 'b',
    ]);
    $lexeme = Lexeme::query()->create(['slug' => 'cmd-lex-'.uniqid(), 'language' => 'en', 'lemma' => 'cmd-word', 'normalized_lemma' => 'cmd-word', 'status' => Lexeme::STATUS_PUBLISHED]);
    $lexeme->examples()->create(['language' => 'en', 'example' => 'Cmd example.', 'translation' => 'т', 'is_primary' => true, 'sort_order' => 1]);

    $this->artisan(IndexRagCorpusCommand::class, ['--all' => true])
        ->assertExitCode(0);

    $response = app(ElasticsearchClient::class)->search($this->ragIndex, [
        'query' => ['match_all' => new stdClass],
    ]);

    expect($response['hits']['hits'])->toHaveCount(2);
});

test('IndexRagCorpusCommand is a no-op when Elasticsearch is disabled', function () {
    config(['elasticsearch.enabled' => false]);

    $this->artisan(IndexRagCorpusCommand::class, ['--all' => true])
        ->assertExitCode(0);
});

test('IndexRagCorpusCommand requires --all or --type with --ids', function () {
    $this->artisan(IndexRagCorpusCommand::class, [])
        ->assertExitCode(1);
});
