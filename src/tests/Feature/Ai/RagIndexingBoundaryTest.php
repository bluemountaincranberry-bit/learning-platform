<?php

use App\Modules\Ai\Application\RagIndexingService;
use App\Contracts\Ai\EmbeddingsClientInterface;
use App\Modules\Ai\Infrastructure\ElasticsearchClient;
use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Domain\Models\GrammarTopic;
use App\Modules\Content\Domain\Models\Lexeme;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['elasticsearch.enabled' => true, 'elasticsearch.rag.index' => 'rag_test']);

    $this->documents = [];
    $client = Mockery::mock(ElasticsearchClient::class);
    $client->shouldReceive('indexExists')->with('rag_test')->andReturn(true);
    $client->shouldReceive('indexDocument')->andReturnUsing(function (string $index, string $id, array $document): void {
        $this->documents[$id] = $document;
    });
    app()->instance(ElasticsearchClient::class, $client);

    $embeddings = Mockery::mock(EmbeddingsClientInterface::class);
    $embeddings->shouldReceive('embed')->andReturn([0.1, 0.2]);
    app()->instance(EmbeddingsClientInterface::class, $embeddings);
});

test('grammar indexing reads only published rules and preserves the indexed text and metadata', function () {
    $topic = GrammarTopic::query()->create(['slug' => 'rag-topic', 'language' => 'en', 'name' => 'RAG', 'status' => 'active']);
    $published = GrammarRule::query()->create([
        'topic_id' => $topic->id, 'slug' => 'rag-published', 'language' => 'en', 'level' => 'B1',
        'title' => 'Present Perfect', 'summary' => 'Past actions', 'body' => 'have/has + participle',
        'status' => GrammarRule::STATUS_PUBLISHED,
    ]);
    $draft = GrammarRule::query()->create([
        'topic_id' => $topic->id, 'slug' => 'rag-draft', 'language' => 'en',
        'title' => 'Draft', 'body' => 'Do not index', 'status' => GrammarRule::STATUS_DRAFT,
    ]);

    expect(app(RagIndexingService::class)->indexGrammarRules([$published->id, $draft->id]))->toBe(1)
        ->and(array_keys($this->documents))->toBe(['grammar_rule:'.$published->id])
        ->and($this->documents['grammar_rule:'.$published->id])->toMatchArray([
            'doc_type' => 'grammar_rule', 'source_id' => $published->id, 'language' => 'en',
            'level' => 'B1', 'title' => 'Present Perfect', 'summary' => 'Past actions',
            'body' => 'have/has + participle', 'text' => "Present Perfect\n\nPast actions\n\nhave/has + participle",
            'embedding' => [0.1, 0.2],
        ]);
});

test('lexeme example indexing reads only published lexemes and preserves the indexed text and metadata', function () {
    $published = Lexeme::query()->create([
        'slug' => 'rag-word', 'language' => 'en', 'lemma' => 'give up',
        'normalized_lemma' => 'give up', 'level' => 'B2', 'status' => Lexeme::STATUS_PUBLISHED,
    ]);
    $example = $published->examples()->create([
        'language' => 'en', 'example' => 'Never give up.', 'translation' => 'Никогда не сдавайся.',
        'is_primary' => true, 'sort_order' => 1,
    ]);
    $draft = Lexeme::query()->create([
        'slug' => 'rag-draft-word', 'language' => 'en', 'lemma' => 'draft',
        'normalized_lemma' => 'draft', 'status' => Lexeme::STATUS_DRAFT,
    ]);
    $draftExample = $draft->examples()->create([
        'language' => 'en', 'example' => 'Never index me.', 'is_primary' => true, 'sort_order' => 1,
    ]);

    expect(app(RagIndexingService::class)->indexLexemeExamples([$example->id, $draftExample->id]))->toBe(1)
        ->and(array_keys($this->documents))->toBe(['lexeme_example:'.$example->id])
        ->and($this->documents['lexeme_example:'.$example->id])->toMatchArray([
            'doc_type' => 'lexeme_example', 'source_id' => $example->id, 'language' => 'en',
            'level' => 'B2', 'lemma' => 'give up', 'example' => 'Never give up.',
            'translation' => 'Никогда не сдавайся.', 'text' => 'Never give up. — Никогда не сдавайся.',
            'embedding' => [0.1, 0.2],
        ]);
});
