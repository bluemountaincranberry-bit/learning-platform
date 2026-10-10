<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Feature
    |--------------------------------------------------------------------------
    |
    | When disabled, AI endpoints (explain lexeme, chat companion) return 503.
    | SPA should hide or disable chat when this is false (e.g. check API or config).
    | Keys and URLs are read from env; no default secrets.
    |
    */

    'enabled' => (bool) env('AI_FEATURE_ENABLED', false),

    'provider' => env('AI_PROVIDER', 'openai'), // openai | ollama

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
    ],

    'transcription' => [
        'openai_model' => env('OPENAI_TRANSCRIPTION_MODEL', 'gpt-4o-mini-transcribe'),
        'local_whisper' => [
            'enabled' => (bool) env('LOCAL_WHISPER_ENABLED', true),
            'base_url' => env('LOCAL_WHISPER_BASE_URL', 'http://whisper:8000/v1'),
            'model' => env('LOCAL_WHISPER_MODEL', 'Systran/faster-whisper-small'),
        ],
    ],

    'azure_speech' => [
        'enabled' => (bool) env('AZURE_SPEECH_ENABLED', false),
        'key' => env('AZURE_SPEECH_KEY'),
        'region' => env('AZURE_SPEECH_REGION'),
    ],

    'ollama' => [
        'url' => env('OLLAMA_URL', 'http://localhost:11434'),
        'model' => env('OLLAMA_MODEL', 'llama3.2'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Embeddings (for recommendations, vector search)
    |--------------------------------------------------------------------------
    |
    | Model and endpoint for embedding vectors. Reuses OpenAI API key when
    | provider is openai; no secrets in repo.
    |
    */
    'embeddings' => [
        'model' => env('AI_EMBEDDINGS_MODEL', 'text-embedding-3-small'),
        'provider' => env('AI_EMBEDDINGS_PROVIDER', 'openai'), // openai | ollama
    ],

    'timeout' => (int) env('AI_TIMEOUT_SECONDS', 30),

    /*
    |--------------------------------------------------------------------------
    | Rate limits (per user per day)
    |--------------------------------------------------------------------------
    |
    | When exceeded, AI explain and chat return 429. Set to 0 to disable limit.
    | See docs/ai-rate-limits.md.
    |
    */
    'rate_limits' => [
        'explain_per_day' => (int) env('AI_RATE_LIMIT_EXPLAIN_PER_DAY', 30),
        'chat_per_day' => (int) env('AI_RATE_LIMIT_CHAT_PER_DAY', 50),
        // Auto-triggered AI candidate extraction for end-user YouTube submissions
        // (not the admin-triggered "Analyze with AI" button, which is unmetered).
        // 0 disables auto-analysis for user submissions entirely.
        'user_submission_analysis_per_day' => (int) env('AI_RATE_LIMIT_USER_SUBMISSION_ANALYSIS_PER_DAY', 3),
        // Speaking practice (SentencePracticeService): counts start+check calls
        // together (both hit the same route group/middleware), each a real LLM call.
        'sentence_practice_per_day' => (int) env('AI_RATE_LIMIT_SENTENCE_PRACTICE_PER_DAY', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Explain cache (reduce repeat AI calls)
    |--------------------------------------------------------------------------
    */
    'explain_cache_enabled' => (bool) env('AI_EXPLAIN_CACHE_ENABLED', true),
    'explain_cache_ttl_seconds' => (int) env('AI_EXPLAIN_CACHE_TTL_SECONDS', 604800), // 7 days

    /*
    |--------------------------------------------------------------------------
    | Lexeme metadata suggestion (task 7.2 + 9.7 — auto-CEFR-level and
    | auto-part-of-speech via SuggestLexemeLevelJob)
    |--------------------------------------------------------------------------
    |
    | Deliberately a separate flag from the top-level `enabled` above, not a
    | reuse of it: `enabled` gates *user-facing, request-time* AI features
    | (explain, chat) where a test can freely flip it per-test without side
    | effects. This job instead fires from a model lifecycle hook
    | (ContentLexeme::booted() -> CanonicalLexemeSyncService::sync()) that
    | many unrelated tests exercise just by creating a ContentLexeme — with
    | QUEUE_CONNECTION=sync in testing, dispatch() runs the job inline, so
    | piggybacking on `enabled` would make every such test silently place a
    | real (faked-or-not) AI call. Keeping this independent, off by default,
    | keeps that blast radius at zero; tests for this feature opt in
    | explicitly by setting both flags.
    |
    | Renamed from `lexeme_level_suggestion` (task 9.7): the same single job
    | and LLM call now suggests both `level` and `part_of_speech` in one
    | response, so the flag name no longer names just one of the two fields
    | it gates. The job class itself keeps its original name
    | (SuggestLexemeLevelJob) — a rename would touch several call sites
    | (CanonicalLexemeSyncService, BackfillLexemeLevelsCommand) for no
    | functional benefit.
    |
    */
    'lexeme_metadata_suggestion' => [
        'enabled' => (bool) env('AI_LEXEME_METADATA_SUGGESTION_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-enrichment of related lexemes (task 10.4)
    |--------------------------------------------------------------------------
    |
    | EnrichLexemeAssociationsJob — dispatched from
    | CanonicalLexemeSyncService::syncLemma() whenever a brand-new canonical
    | Lexeme is created, reusing LexemeEnrichmentService::propose() (the same
    | AI call as the admin-triggered "AI: Enrich" action) but persisting the
    | top few proposed relations automatically instead of waiting for an
    | admin to review them — the "everything related gets added too" half of
    | task 10.4. Same isolation rationale as `lexeme_metadata_suggestion`
    | above (separate from the top-level `enabled` kill switch, off by
    | default) — this job also fires from a model-creation path many
    | unrelated tests exercise under QUEUE_CONNECTION=sync.
    |
    */
    'lexeme_relations_enrichment' => [
        'enabled' => (bool) env('AI_LEXEME_RELATIONS_ENRICHMENT_ENABLED', false),
        'max_per_lexeme' => (int) env('AI_LEXEME_RELATIONS_ENRICHMENT_MAX', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Semantic cache (task 5.4 — ai-engineering-learning-roadmap.md step 7)
    |--------------------------------------------------------------------------
    |
    | Conceptually the same idea as the explain cache above (Cache::put with
    | a TTL, reduce repeat AI calls) extended to questions that are *worded
    | differently but mean the same thing* — matched by embedding similarity
    | (SemanticCacheService, ai_semantic_cache_entries table) instead of an
    | exact cache key. `similarity_threshold` is deliberately high: 0.92+
    | cosine similarity on text-embedding-3-small is near-duplicate phrasing
    | ("what is present perfect" vs "explain present perfect to me"), not
    | just "same topic" (related-but-different questions score noticeably
    | lower) — a looser threshold risks serving a wrong cached answer for a
    | different question, which is worse than the cost saved by caching it.
    |
    */
    'semantic_cache' => [
        'enabled' => (bool) env('AI_SEMANTIC_CACHE_ENABLED', true),
        'similarity_threshold' => (float) env('AI_SEMANTIC_CACHE_SIMILARITY_THRESHOLD', 0.92),
        'ttl_seconds' => (int) env('AI_SEMANTIC_CACHE_TTL_SECONDS', 604800), // 7 days
        // Bounds the linear cosine scan per scope, same "small, bounded
        // collection" reasoning as CandidateMatchingService.
        'max_candidates' => (int) env('AI_SEMANTIC_CACHE_MAX_CANDIDATES', 500),
    ],

    /*
    |--------------------------------------------------------------------------
    | Content analysis (AI candidate extraction: lexemes + grammar)
    |--------------------------------------------------------------------------
    */
    'analysis' => [
        'translation_language' => env('AI_ANALYSIS_TRANSLATION_LANGUAGE', 'ru'),
        'max_transcript_chars' => (int) env('AI_ANALYSIS_MAX_TRANSCRIPT_CHARS', 8000),
        // Lesson notes are analyzed in parts of at most this many characters,
        // so a long handout/PDF is covered end to end (VIK-70). Small enough that
        // one part's answer finishes inside the provider timeout (ai.timeout).
        'lesson_chunk_chars' => (int) env('AI_ANALYSIS_LESSON_CHUNK_CHARS', 2500),
        'match_threshold' => (float) env('AI_ANALYSIS_MATCH_THRESHOLD', 0.85),
        'min_lexeme_confidence' => (float) env('AI_ANALYSIS_MIN_LEXEME_CONFIDENCE', 0.7),
        // Fraction (0-1) of the transcript's distinct tokenizer-normalized words
        // that must appear in at least one lexeme candidate. Below this, a
        // `focused` run auto-retries once with `thoroughness=thorough` (task 9.1).
        'min_coverage_pct' => (float) env('AI_ANALYSIS_MIN_COVERAGE_PCT', 0.7),
    ],

    /*
    |--------------------------------------------------------------------------
    | Grammar exercises (AI-generated practice items, admin-triggered)
    |--------------------------------------------------------------------------
    */
    'exercises' => [
        'default_count' => (int) env('AI_EXERCISES_DEFAULT_COUNT', 5),
        'max_count' => (int) env('AI_EXERCISES_MAX_COUNT', 10),

        // Learner-triggered generation for grammar practice (VIK-31): the
        // first batch fills an empty rule (3 per type), top-ups keep at least
        // `top_up_below` unseen exercises, a round starts at `min_to_start`.
        'practice' => [
            'first_batch' => (int) env('AI_GRAMMAR_PRACTICE_FIRST_BATCH', 15),
            'top_up_batch' => (int) env('AI_GRAMMAR_PRACTICE_TOP_UP_BATCH', 10),
            'top_up_below' => (int) env('AI_GRAMMAR_PRACTICE_TOP_UP_BELOW', 10),
            'min_to_start' => (int) env('AI_GRAMMAR_PRACTICE_MIN_TO_START', 5),
            'daily_batches_per_rule' => (int) env('AI_GRAMMAR_PRACTICE_DAILY_BATCHES', 3),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | "Ready to watch" exam (ContentReadinessService)
    |--------------------------------------------------------------------------
    |
    | pass_threshold_pct is snapshotted onto each content_exam_attempts row
    | at completion time, so changing it here never rewrites the meaning of
    | past attempts. Unlimited retakes by design (no cooldown) — the exam is
    | a learning gate, not an anti-cheat surface.
    |
    */
    'exam' => [
        'pass_threshold_pct' => (float) env('AI_EXAM_PASS_THRESHOLD_PCT', 80),
        'card_count' => (int) env('AI_EXAM_CARD_COUNT', 8),
    ],

    /*
    |--------------------------------------------------------------------------
    | Grammar warm-up (ContentGrammarPreExamController)
    |--------------------------------------------------------------------------
    |
    | card_count is the *default total* split across however many grammar
    | rules the learner selects (ContentGrammarPreExamController::start()),
    | not a per-rule count — see SentencePracticeService::generateForRules().
    | No pass_threshold here: unlike the "Ready to watch" exam this isn't a
    | gate, just a per-topic confidence measurement.
    |
    */
    'pre_exam' => [
        'card_count' => (int) env('AI_PRE_EXAM_CARD_COUNT', 6),
    ],

    /*
    |--------------------------------------------------------------------------
    | Content authoring agent (admin chat + PDF -> draft lesson)
    |--------------------------------------------------------------------------
    |
    | Tool calling requires OpenAI (see AiToolCallingClient) — this is enabled
    | independently of the general `enabled` flag above so it can be turned
    | off without disabling explain/chat, and vice versa.
    |
    */
    'agent' => [
        'enabled' => (bool) env('AI_AGENT_ENABLED', false),
        'turns_per_day' => (int) env('AI_AGENT_TURNS_PER_DAY', 100),
        'max_upload_kb' => (int) env('AI_AGENT_MAX_UPLOAD_KB', 10240),

        /*
        | agent_type (AgentConversation::agent_type) => AgentService FQCN.
        | RunAgentTurnJob resolves through this registry instead of being
        | hardcoded to one agent — see
        | docs/architecture/agent-framework-roadmap.md, step 5.7.
        */
        'registry' => [
            \App\Modules\Ai\Application\Agent\ContentAgentService::AGENT_TYPE => \App\Modules\Ai\Application\Agent\ContentAgentService::class,
            \App\Modules\Ai\Application\Agent\StudentTutorAgentService::AGENT_TYPE => \App\Modules\Ai\Application\Agent\StudentTutorAgentService::class,
            \App\Modules\Ai\Application\Agent\LessonAgentService::AGENT_TYPE => \App\Modules\Ai\Application\Agent\LessonAgentService::class,
        ],

        /*
        | Specialist agents that are never an `AgentConversation.agent_type`
        | of their own (a conversation is never routed to them directly —
        | `registry` above stays authoritative for that) but ARE reached
        | via handoff (`HandoffToGrammarAgentTool`/`HandoffToReviewAgentTool`)
        | and, via `GrammarAgentGraphNode`/`ReviewAgentGraphNode`, as a
        | `StudyPlanGraph` branch. Kept separate from `registry` rather than
        | merged into it — merging would make `RunAgentTurnJob` able to
        | resolve a conversation directly to a specialist, bypassing the
        | tutor's own routing/handoff logic, which is not what this list is
        | for; it exists purely so admin-facing listings (prompt/agent
        | catalog) can show every agent with a real system prompt, not just
        | conversation-routable ones.
        */
        'specialists' => [
            \App\Modules\Ai\Application\Agent\GrammarAgentService::AGENT_TYPE => \App\Modules\Ai\Application\Agent\GrammarAgentService::class,
            \App\Modules\Ai\Application\Agent\ReviewAgentService::AGENT_TYPE => \App\Modules\Ai\Application\Agent\ReviewAgentService::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Agent tracing (Tier 1 — see docs/architecture/agent-framework-roadmap.md, section 9)
    |--------------------------------------------------------------------------
    |
    | When disabled, SpanRecorder resolves to the no-op NullSpanRecorder (the
    | default in tests and local dev). Enable to persist agent_trace_spans via
    | DatabaseSpanRecorder. Pricing assumes the single model currently in use
    | (gpt-4o-mini, see OpenAiClient) — revisit if multiple models get mixed.
    |
    */
    'tracing' => [
        'enabled' => (bool) env('AI_TRACING_ENABLED', false),
    ],

    'pricing' => [
        'prompt_per_1k_usd' => (float) env('AI_PRICE_PROMPT_PER_1K_USD', 0.00015),
        'completion_per_1k_usd' => (float) env('AI_PRICE_COMPLETION_PER_1K_USD', 0.0006),
    ],

    /*
    |--------------------------------------------------------------------------
    | Grammar rule examples (VIK-39)
    |--------------------------------------------------------------------------
    |
    | "More examples" on the rule page queues `more_count` AI examples; a
    | learner gets `daily_batches_per_rule` batches per rule per day. The
    | backfill command (grammar:backfill-examples) tops every rule up to
    | `min_per_rule`, translated into `translation_language`.
    |
    */
    'examples' => [
        'more_count' => (int) env('AI_GRAMMAR_EXAMPLES_MORE_COUNT', 4),
        'daily_batches_per_rule' => (int) env('AI_GRAMMAR_EXAMPLES_DAILY_BATCHES', 3),
        'min_per_rule' => (int) env('AI_GRAMMAR_EXAMPLES_MIN_PER_RULE', 6),
        'backfill_target' => (int) env('AI_GRAMMAR_EXAMPLES_BACKFILL_TARGET', 8),
        'translation_language' => env('AI_GRAMMAR_EXAMPLES_TRANSLATION_LANGUAGE', 'ru'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Graph node registry (task 4.10)
    |--------------------------------------------------------------------------
    |
    | Explicit node_key => GraphNode FQCN map, resolved through the
    | container by GraphNodeRegistry — same principle as agent.registry
    | above and the explicit tool list in AiServiceProvider: a
    | GraphDefinition can be built from data (which of these keys, in what
    | order — see AiAnalysisGraph), but the set of node *types* that can
    | ever run stays this closed, reviewed list.
    |
    */
    'graph' => [
        'node_registry' => [
            'analyze' => \App\Modules\Ai\Application\Agent\Graph\Nodes\AnalyzeNode::class,
            'match' => \App\Modules\Ai\Application\Agent\Graph\Nodes\MatchNode::class,
            'human_checkpoint' => \App\Modules\Ai\Application\Agent\Graph\Nodes\HumanCheckpointNode::class,
            'apply' => \App\Modules\Ai\Application\Agent\Graph\Nodes\ApplyNode::class,
            'grammar_agent_branch' => \App\Modules\Ai\Application\Agent\Graph\Nodes\GrammarAgentGraphNode::class,
            'review_agent_branch' => \App\Modules\Ai\Application\Agent\Graph\Nodes\ReviewAgentGraphNode::class,
        ],

        /*
        | graph_name (agent_graph_runs.graph_name) => FQCN exposing
        | definition(): GraphDefinition — used by GraphDefinitionResolver
        | (task 4.11) so ResumeGraphJob can rebuild the right definition
        | from just the persisted run row after a ParallelNode fan-out.
        */
        'definitions' => [
            \App\Modules\Ai\Application\Agent\Graph\Definitions\AiAnalysisGraph::NAME => \App\Modules\Ai\Application\Agent\Graph\Definitions\AiAnalysisGraph::class,
            \App\Modules\Ai\Application\Agent\Graph\Definitions\TutorRoutingGraph::NAME => \App\Modules\Ai\Application\Agent\Graph\Definitions\TutorRoutingGraph::class,
            \App\Modules\Ai\Application\Agent\Graph\Definitions\StudyPlanGraph::NAME => \App\Modules\Ai\Application\Agent\Graph\Definitions\StudyPlanGraph::class,
        ],
    ],

];
