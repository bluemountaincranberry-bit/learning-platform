<?php

/*
|--------------------------------------------------------------------------
| Elasticsearch (RAG corpus — EPIC 2, task 2.2)
|--------------------------------------------------------------------------
|
| Elasticsearch is already running in docker-compose.yml but nothing in the
| codebase used it before this. This file owns the connection + the RAG
| index mapping (dense_vector kNN) — the first real consumer, per
| docs/architecture/ai-platform-vision.md section 9.3, point 3.
|
| Do NOT reuse this index for anything else: section 9.3 explicitly calls
| out three separate "similarity" use cases that must not be conflated —
| (1) catalog dedup matching stays in Postgres (CandidateMatchingService,
| untouched), (2) semantic memory about a student is a *separate* ES index
| (task 2.6, blocked on EPIC 4.13, not created here), (3) this RAG corpus
| index is the general "what is in the learning materials" search — grammar
| rule bodies + curated lexeme examples, task 2.1.
|
| A fourth, unrelated use of this same Elasticsearch instance was added in
| EPIC 5 (task 5.3, `trace_spans` section below) — Tier 2 tracing export.
| It shares the connection (`hosts` above) but is its own index, for the
| same "different schema/lifecycle, don't conflate" reason as (1)-(3).
|
| `enabled` gates every read/write here (index/search calls become no-ops
| when false) the same way `ai.enabled`/`ai.agent.enabled` gate the rest of
| the AI feature surface — safe default off, explicit opt-in.
|
*/

return [

    'enabled' => (bool) env('ELASTICSEARCH_ENABLED', false),

    'hosts' => [
        sprintf('http://%s:%s', env('ELASTICSEARCH_HOST', 'localhost'), env('ELASTICSEARCH_PORT', '9200')),
    ],

    /*
    |--------------------------------------------------------------------------
    | RAG corpus index (task 2.1-2.4)
    |--------------------------------------------------------------------------
    |
    | Corpus = GrammarRule.body (+ title/summary for a richer embedding
    | input) and LexemeExample (example + translation) — task 2.1. No new
    | content type is introduced; both are indexed as different `doc_type`
    | values in one index (their document shapes overlap enough that a
    | second index isn't earning its keep yet — revisit only if the schemas
    | actually diverge).
    |
    | `dims` must match the embeddings model in config('ai.embeddings.model')
    | — text-embedding-3-small produces 1536-dimensional vectors.
    |
    | `min_score` filters kNN hits after search: Elasticsearch kNN always
    | returns up to `k` nearest neighbours even when none are a good match,
    | so a floor is required to keep genuinely irrelevant documents out of
    | the top-K fed to the model (task 2.4 acceptance test). Cosine
    | similarity dense_vector scores are normalized by Elasticsearch to
    | (1 + cosine) / 2, i.e. a 0..1 range where 0.5 is "orthogonal" — 0.6 is
    | a deliberately conservative floor, not a tuned production value.
    |
    */
    'rag' => [
        'index' => env('ELASTICSEARCH_RAG_INDEX', 'rag_corpus'),
        'dims' => (int) env('AI_EMBEDDINGS_DIMENSIONS', 1536),
        'min_score' => (float) env('ELASTICSEARCH_RAG_MIN_SCORE', 0.6),

        'mappings' => [
            'properties' => [
                'doc_type' => ['type' => 'keyword'],   // 'grammar_rule' | 'lexeme_example'
                'source_id' => ['type' => 'integer'],   // grammar_rules.id | lexeme_examples.id
                'language' => ['type' => 'keyword'],
                'level' => ['type' => 'keyword'],
                'title' => ['type' => 'text'],
                'summary' => ['type' => 'text'],
                'body' => ['type' => 'text'],
                'lemma' => ['type' => 'keyword'],
                'example' => ['type' => 'text'],
                'translation' => ['type' => 'text'],
                // The exact string that was embedded — the source of truth
                // for what the vector "means", used as a display fallback.
                'text' => ['type' => 'text'],
                'embedding' => [
                    'type' => 'dense_vector',
                    'dims' => (int) env('AI_EMBEDDINGS_DIMENSIONS', 1536),
                    'index' => true,
                    'similarity' => 'cosine',
                ],
                'indexed_at' => ['type' => 'date'],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Trace span export index (task 5.3 — Tier 2 tracing)
    |--------------------------------------------------------------------------
    |
    | Not a new tracing framework (see agent-framework-roadmap.md section 9,
    | "Tier 2"): Postgres `agent_trace_spans` (task 1.7) remains the source
    | of truth, this index is an additive copy for Kibana Discover-style
    | search/dashboards once there is enough concurrent agent traffic that
    | ad-hoc SQL against Postgres stops being convenient. ElasticSpanExporter
    | marks each row it exports (`agent_trace_spans.exported_at`) so re-runs
    | only ship newly-closed spans.
    |
    | `metadata` is `flattened`, not `object` with an explicit schema — span
    | metadata varies by span_type (iteration/max_iterations for llm_call,
    | handoff depth for handoff, ...) and is not itself something this index
    | needs to run structured aggregations over; `flattened` avoids a mapping
    | explosion from indexing every possible metadata key as its own field
    | while still keeping it visible in Kibana Discover.
    |
    */
    'trace_spans' => [
        'index' => env('ELASTICSEARCH_TRACE_SPANS_INDEX', 'agent_trace_spans'),

        'mappings' => [
            'properties' => [
                'trace_id' => ['type' => 'keyword'],
                'span_id' => ['type' => 'keyword'],
                'parent_span_id' => ['type' => 'keyword'],
                'span_type' => ['type' => 'keyword'],
                'name' => ['type' => 'keyword'],
                'agent_type' => ['type' => 'keyword'],
                'started_at' => ['type' => 'date'],
                'ended_at' => ['type' => 'date'],
                'duration_ms' => ['type' => 'integer'],
                'prompt_tokens' => ['type' => 'integer'],
                'completion_tokens' => ['type' => 'integer'],
                'cost_usd' => ['type' => 'float'],
                'status' => ['type' => 'keyword'],
                'metadata' => ['type' => 'flattened'],
            ],
        ],
    ],

];
