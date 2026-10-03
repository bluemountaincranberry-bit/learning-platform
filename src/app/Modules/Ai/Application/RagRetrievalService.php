<?php

namespace App\Modules\Ai\Application;

use App\Contracts\Ai\EmbeddingsClientInterface;
use App\Modules\Ai\Infrastructure\ElasticsearchClient;

/**
 * Task 2.4: embed(query) -> Elasticsearch kNN -> top-K documents from the
 * RAG corpus indexed by RagIndexingService (task 2.3).
 *
 * This is intentionally a thin, general-purpose retrieval helper — not
 * specific to any one tool — so ExplainGrammarTool/FindExamplesTool (task
 * 2.5) both depend on it without duplicating the ES query shape. Filters by
 * `doc_type` so each tool only ever sees the corpus slice it's meant to
 * (grammar_rule vs lexeme_example) — see config/elasticsearch.php for why
 * these share one index instead of two.
 *
 * A no-op (empty result) when Elasticsearch is disabled or the index
 * doesn't exist yet (nothing indexed) — never a hard failure for those two
 * expected states. Any other Elasticsearch error is left to propagate:
 * AgentLoop already converts an uncaught Throwable from a tool into a
 * generic error result fed back to the model (see AgentTool docblock), so
 * swallowing here would just hide a real infrastructure problem for no
 * benefit.
 */
class RagRetrievalService
{
    public function __construct(
        private readonly EmbeddingsClientInterface $embeddings,
        private readonly ElasticsearchClient $client,
    ) {}

    /**
     * @return array<int, array{doc_type: string, source_id: int, language: ?string, level: ?string, title: ?string, summary: ?string, body: ?string, lemma: ?string, example: ?string, translation: ?string, text: string, score: float}>
     */
    public function retrieve(string $query, ?string $docType = null, ?string $language = null, int $topK = 5): array
    {
        $query = trim($query);
        if ($query === '' || ! config('elasticsearch.enabled', false)) {
            return [];
        }

        $vector = $this->embeddings->embed($query);

        $filters = [];
        if ($docType !== null) {
            $filters[] = ['term' => ['doc_type' => $docType]];
        }
        if ($language !== null && $language !== '') {
            $filters[] = ['term' => ['language' => $language]];
        }

        $knn = [
            'field' => 'embedding',
            'query_vector' => $vector,
            'k' => $topK,
            'num_candidates' => max(50, $topK * 10),
        ];
        if ($filters !== []) {
            $knn['filter'] = count($filters) === 1 ? $filters[0] : ['bool' => ['filter' => $filters]];
        }

        // ElasticsearchClient::search() itself returns an empty hits array
        // for a 404 (the RAG index hasn't been created yet — nothing
        // indexed for this corpus/environment) instead of throwing, so that
        // expected state doesn't need special-casing here.
        $response = $this->client->search(config('elasticsearch.rag.index'), [
            'knn' => $knn,
            'size' => $topK,
            '_source' => ['doc_type', 'source_id', 'language', 'level', 'title', 'summary', 'body', 'lemma', 'example', 'translation', 'text'],
        ]);

        $hits = $response['hits']['hits'] ?? [];
        $minScore = (float) config('elasticsearch.rag.min_score', 0.6);

        $results = [];
        foreach ($hits as $hit) {
            $score = (float) ($hit['_score'] ?? 0);
            // kNN always returns up to k neighbours even when none are truly
            // relevant — this floor is what keeps an irrelevant document out
            // of the top-K handed to the model (task 2.4 acceptance test).
            if ($score < $minScore) {
                continue;
            }
            $results[] = array_merge(['score' => $score], $hit['_source']);
        }

        return $results;
    }

    /**
     * Wraps retrieved passages in explicit `<tool_output>` boundaries — the
     * same untrusted-external-text mitigation already used by
     * ExtractPdfTextTool (task 1.9/5.9): RAG documents come from Blue's own
     * corpus, not user input, but the AI content-analysis pipeline can be
     * the thing that put them there, so they get the same "this is data,
     * not instructions" framing before reaching the model.
     *
     * @param  array<int, array{text?: string}>  $documents
     */
    public function wrapAsToolOutput(array $documents): string
    {
        $blocks = array_filter(array_map(
            fn (array $doc) => trim((string) ($doc['text'] ?? '')),
            $documents
        ));

        if ($blocks === []) {
            return '';
        }

        return "<tool_output>\n".implode("\n---\n", $blocks)."\n</tool_output>";
    }
}
