<?php

namespace App\Modules\Ai\Application;

use App\Contracts\Ai\EmbeddingsClientInterface;
use App\Modules\Ai\Infrastructure\ElasticsearchClient;
use App\Modules\Content\Application\Contracts\RagSourceReaderInterface;

/**
 * Task 2.3: computes embeddings for the RAG corpus (task 2.1 — GrammarRule
 * bodies + curated LexemeExample sentences) and indexes them into
 * Elasticsearch (mapping defined in config/elasticsearch.php, task 2.2).
 *
 * Deliberately synchronous, not queued: unlike ComputeGrammarRuleEmbeddingsJob
 * (which the Artisan command layer here mirrors — see
 * IndexRagCorpusCommand), each document here is a single embed-and-index
 * round trip with no fan-out benefit at this corpus size — adding a queued
 * job would be complexity without a payoff (engineering-principles.md).
 * Revisit if the corpus grows large enough that `--all` becomes slow.
 *
 * A no-op when config('elasticsearch.enabled') is false — same safe-default
 * pattern as the rest of the AI feature surface (ai.enabled, ai.agent.enabled).
 */
class RagIndexingService
{
    public function __construct(
        private readonly EmbeddingsClientInterface $embeddings,
        private readonly ElasticsearchClient $client,
        private readonly RagSourceReaderInterface $sources,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('elasticsearch.enabled', false);
    }

    /**
     * Creates the RAG index with its dense_vector mapping if it doesn't
     * already exist. Safe to call repeatedly (checked, not blind create).
     */
    public function ensureIndexExists(): void
    {
        $index = $this->indexName();

        if ($this->client->indexExists($index)) {
            return;
        }

        $this->client->createIndex($index, config('elasticsearch.rag.mappings'));
    }

    /**
     * @param  array<int, int>|null  $ids  Null indexes every published grammar rule.
     * @return int Number of documents indexed.
     */
    public function indexGrammarRules(?array $ids = null): int
    {
        if (! $this->enabled()) {
            return 0;
        }

        $this->ensureIndexExists();

        $count = 0;
        foreach ($this->sources->publishedGrammarRules($ids) as $rule) {
            // Title/summary are included in the embedding input (not just
            // body) so a query like "present perfect" still matches even
            // when the body text itself doesn't repeat the topic name —
            // but `body` remains the field actually shown to the model
            // (task 2.1: the corpus item *is* GrammarRule.body).
            $text = trim(collect([$rule['title'], $rule['summary'], $rule['body']])->filter()->implode("\n\n"));
            if ($text === '') {
                continue;
            }

            $vector = $this->embeddings->embed($text);

            $this->client->indexDocument($this->indexName(), 'grammar_rule:'.$rule['id'], [
                'doc_type' => 'grammar_rule',
                'source_id' => $rule['id'],
                'language' => $rule['language'],
                'level' => $rule['level'],
                'title' => $rule['title'],
                'summary' => $rule['summary'],
                'body' => $rule['body'],
                'text' => $text,
                'embedding' => $vector,
                'indexed_at' => now()->toIso8601String(),
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * @param  array<int, int>|null  $ids  Null indexes every example belonging to a published lexeme.
     * @return int Number of documents indexed.
     */
    public function indexLexemeExamples(?array $ids = null): int
    {
        if (! $this->enabled()) {
            return 0;
        }

        $this->ensureIndexExists();

        $count = 0;
        foreach ($this->sources->publishedLexemeExamples($ids) as $example) {
            $text = trim($example['example'].($example['translation'] ? ' — '.$example['translation'] : ''));
            if ($text === '') {
                continue;
            }

            $vector = $this->embeddings->embed($text);

            $this->client->indexDocument($this->indexName(), 'lexeme_example:'.$example['id'], [
                'doc_type' => 'lexeme_example',
                'source_id' => $example['id'],
                'language' => $example['language'],
                'level' => $example['level'],
                'lemma' => $example['lemma'],
                'example' => $example['example'],
                'translation' => $example['translation'],
                'text' => $text,
                'embedding' => $vector,
                'indexed_at' => now()->toIso8601String(),
            ]);
            $count++;
        }

        return $count;
    }

    private function indexName(): string
    {
        return config('elasticsearch.rag.index', 'rag_corpus');
    }
}
