<?php

use App\Modules\Ai\Application\Agent\Data\AgentToolContext;
use App\Modules\Ai\Application\Agent\Data\AgentToolDefinition;
use App\Modules\Ai\Application\Agent\Tools\Student\ExplainGrammarTool;
use App\Modules\Ai\Application\Agent\Tools\Student\FindExamplesTool;
use App\Modules\Ai\Application\Agent\Tools\Student\SearchVocabularyTool;
use App\Modules\Ai\Application\RagIndexingService;
use App\Modules\Ai\Infrastructure\ElasticsearchClient;
use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Domain\Models\GrammarTopic;
use App\Modules\Content\Domain\Models\Lexeme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

// --- RAG test harness (task 2.5) --------------------------------------
//
// ExplainGrammarTool/FindExamplesTool now retrieve through
// RagRetrievalService -> real Elasticsearch (already running in this
// environment) with only the embeddings HTTP call faked — same convention
// as ComputeGrammarRuleEmbeddingsJobTest. Each test gets its own uniquely
// named index (config override) so tests never see each other's documents,
// and the index is dropped again afterwards.

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

// --- SearchVocabularyTool -------------------------------------------------

test('sideEffect is read_only for SearchVocabularyTool', function () {
    expect(app(SearchVocabularyTool::class)->definition()->sideEffect)->toBe(AgentToolDefinition::SIDE_EFFECT_READ_ONLY);
});

test('search vocabulary finds published lexemes matching the query', function () {
    Lexeme::query()->create(['slug' => 'en-run4', 'language' => 'en', 'lemma' => 'run', 'normalized_lemma' => 'run', 'status' => 'published', 'level' => 'A2']);
    Lexeme::query()->create(['slug' => 'en-runner', 'language' => 'en', 'lemma' => 'runner', 'normalized_lemma' => 'runner', 'status' => 'published', 'level' => 'B1']);
    Lexeme::query()->create(['slug' => 'en-draft-run', 'language' => 'en', 'lemma' => 'running', 'normalized_lemma' => 'running', 'status' => 'draft', 'level' => 'A2']);

    $result = app(SearchVocabularyTool::class)->execute(['query' => 'run'], new AgentToolContext(1, 1));

    expect($result['result_count'])->toBe(2)
        ->and(collect($result['results'])->pluck('lemma')->sort()->values()->all())->toBe(['run', 'runner']);
});

test('search vocabulary requires a query argument', function () {
    $result = app(SearchVocabularyTool::class)->execute([], new AgentToolContext(1, 1));

    expect($result)->toHaveKey('error');
});

test('search vocabulary applies language and limit and preserves the public result shape', function () {
    Lexeme::query()->create(['slug' => 'en-running', 'language' => 'en', 'lemma' => 'running', 'normalized_lemma' => 'running', 'status' => 'published', 'level' => 'B1', 'part_of_speech' => 'verb']);
    Lexeme::query()->create(['slug' => 'en-run', 'language' => 'en', 'lemma' => 'run', 'normalized_lemma' => 'run', 'status' => 'published', 'level' => 'A2', 'part_of_speech' => 'verb']);
    Lexeme::query()->create(['slug' => 'fr-run', 'language' => 'fr', 'lemma' => 'run', 'normalized_lemma' => 'run', 'status' => 'published', 'level' => 'A2']);

    $result = app(SearchVocabularyTool::class)->execute(
        ['query' => 'run', 'language' => ' EN ', 'limit' => 1],
        new AgentToolContext(1, 1),
    );

    expect($result)->toBe([
        'result_count' => 1,
        'results' => [[
            'lemma' => 'run',
            'language' => 'en',
            'level' => 'A2',
            'part_of_speech' => 'verb',
        ]],
    ]);
});

// --- ExplainGrammarTool (task 2.5: RagRetrievalService over Elasticsearch) -

test('sideEffect is read_only for ExplainGrammarTool', function () {
    expect(app(ExplainGrammarTool::class)->definition()->sideEffect)->toBe(AgentToolDefinition::SIDE_EFFECT_READ_ONLY);
});

test('explain grammar returns a matching published rule with examples', function () {
    $topic = GrammarTopic::query()->create(['slug' => 'pp-topic-'.uniqid(), 'language' => 'en', 'name' => 'Present Perfect', 'status' => 'active']);
    $rule = GrammarRule::query()->create([
        'topic_id' => $topic->id, 'slug' => 'pp-rule-'.uniqid(), 'language' => 'en',
        'title' => 'Present Perfect', 'status' => 'published', 'summary' => 'Used for past actions with present relevance.',
        'body' => 'have/has + past participle',
    ]);
    $rule->examples()->create(['language' => 'en', 'example' => 'I have finished.', 'translation' => 'Я закончил.', 'is_primary' => true, 'sort_order' => 1]);

    app(RagIndexingService::class)->indexGrammarRules([$rule->id]);

    $result = app(ExplainGrammarTool::class)->execute(['topic' => 'present perfect'], new AgentToolContext(1, 1));

    expect($result['found'])->toBeTrue()
        ->and($result['title'])->toBe('Present Perfect')
        ->and($result['body'])->toContain('<tool_output>')
        ->and($result['examples'])->toHaveCount(1);
});

test('explain grammar reports not found for an unmatched topic', function () {
    $result = app(ExplainGrammarTool::class)->execute(['topic' => 'nonexistent construction'], new AgentToolContext(1, 1));

    expect($result['found'])->toBeFalse()->and($result)->toHaveKey('note');
});

test('explain grammar does not return draft rules', function () {
    $topic = GrammarTopic::query()->create(['slug' => 'draft-topic-'.uniqid(), 'language' => 'en', 'name' => 'Draft Topic', 'status' => 'active']);
    $rule = GrammarRule::query()->create([
        'topic_id' => $topic->id, 'slug' => 'draft-rule-'.uniqid(), 'language' => 'en',
        'title' => 'Draft Grammar Rule', 'status' => 'draft',
    ]);

    // Draft rules are never indexed by RagIndexingService (it only queries
    // status=published) — asserts that invariant directly rather than just
    // asserting the tool's end behavior.
    $indexedCount = app(RagIndexingService::class)->indexGrammarRules([$rule->id]);
    expect($indexedCount)->toBe(0);

    $result = app(ExplainGrammarTool::class)->execute(['topic' => 'draft grammar'], new AgentToolContext(1, 1));

    expect($result['found'])->toBeFalse();
});

// --- FindExamplesTool (task 2.5: RagRetrievalService over Elasticsearch) --

test('sideEffect is read_only for FindExamplesTool', function () {
    expect(app(FindExamplesTool::class)->definition()->sideEffect)->toBe(AgentToolDefinition::SIDE_EFFECT_READ_ONLY);
});

test('find examples returns curated examples for a matching lexeme', function () {
    $lexeme = Lexeme::query()->create(['slug' => 'en-giveup', 'language' => 'en', 'lemma' => 'give up', 'normalized_lemma' => 'give up', 'status' => 'published']);
    $example = $lexeme->examples()->create(['language' => 'en', 'example' => 'Never give up.', 'translation' => 'Никогда не сдавайся.', 'is_primary' => true, 'sort_order' => 1]);

    app(RagIndexingService::class)->indexLexemeExamples([$example->id]);

    $result = app(FindExamplesTool::class)->execute(['word' => 'give up'], new AgentToolContext(1, 1));

    expect($result['found'])->toBeTrue()
        ->and($result['examples'])->toHaveCount(1)
        ->and($result['examples'][0]['example'])->toBe('Never give up.')
        ->and($result['context'])->toContain('<tool_output>');
});

test('find examples reports not found when no examples exist', function () {
    $result = app(FindExamplesTool::class)->execute(['word' => 'nonexistent phrase'], new AgentToolContext(1, 1));

    expect($result['found'])->toBeFalse()->and($result['examples'])->toBe([]);
});
