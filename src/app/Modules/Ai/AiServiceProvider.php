<?php

namespace App\Modules\Ai;

use App\Contracts\Ai\AiAnalysisRunDispatcher;
use App\Contracts\Ai\AiClientInterface;
use App\Contracts\Ai\AiFieldEditCapability;
use App\Contracts\Ai\AiJsonClient;
use App\Contracts\Ai\AiStreamingChatClient;
use App\Contracts\Ai\AiToolCallingClient;
use App\Contracts\Ai\ChatAiServiceInterface;
use App\Contracts\Ai\ContentAnalysisCapability;
use App\Contracts\Ai\ContentExamGenerationCapability;
use App\Contracts\Ai\ContextSentenceGenerationCapability;
use App\Contracts\Ai\EmbeddingsClientInterface;
use App\Contracts\Ai\GrammarExerciseGenerationDispatcher;
use App\Contracts\Ai\GrammarRuleExampleGenerationDispatcher;
use App\Contracts\Ai\InterviewConversationGateway;
use App\Contracts\Ai\LessonAssistant;
use App\Contracts\Ai\LexemeEnrichmentCapability;
use App\Contracts\Ai\LexemeEnrichmentDispatcher;
use App\Contracts\Ai\LexemeExplanationCapability;
use App\Contracts\Ai\LexemeMetadataSuggestionCapability;
use App\Contracts\Ai\LexemeTranslationCapability;
use App\Contracts\Ai\ManualLexemeCandidateCapability;
use App\Contracts\Ai\PromptRegistryInterface;
use App\Contracts\Ai\SentenceAnswerGradingCapability;
use App\Contracts\Ai\SentenceGenerationCapability;
use App\Contracts\Ai\TextTranslationCapability;
use App\Modules\Ai\Application\Agent\AgentLoop;
use App\Modules\Ai\Application\Agent\ContentAgentService;
use App\Modules\Ai\Application\Agent\Contracts\AgentTool;
use App\Modules\Ai\Application\Agent\Data\AgentBlueprint;
use App\Modules\Ai\Application\Agent\Data\AgentToolDefinition;
use App\Modules\Ai\Application\Agent\GrammarAgentService;
use App\Modules\Ai\Application\Agent\Graph\GraphNodeRegistry;
use App\Modules\Ai\Application\Agent\Graph\GraphRunStatusStreamer;
use App\Modules\Ai\Application\Agent\InterviewAgentService;
use App\Modules\Ai\Application\Agent\LessonAgentService;
use App\Modules\Ai\Application\Agent\ReviewAgentService;
use App\Modules\Ai\Application\Agent\StudentTutorAgentService;
use App\Modules\Ai\Application\Agent\Tools\Handoff\HandoffToGrammarAgentTool;
use App\Modules\Ai\Application\Agent\Tools\Handoff\HandoffToReviewAgentTool;
use App\Modules\Ai\Application\Agent\Tools\HandoffTool;
use App\Modules\Ai\Application\Agent\Tracing\DatabaseSpanRecorder;
use App\Modules\Ai\Application\Agent\Tracing\NullSpanRecorder;
use App\Modules\Ai\Application\Agent\Tracing\SpanRecorder;
use App\Modules\Ai\Application\AiAnalysisRunDispatchService;
use App\Modules\Ai\Application\AiContentAnalysisService;
use App\Modules\Ai\Application\AiFieldEditService;
use App\Modules\Ai\Application\Capabilities\ContextSentenceGenerationService;
use App\Modules\Ai\Application\Capabilities\LexemeExplanationService;
use App\Modules\Ai\Application\Capabilities\LexemeMetadataSuggestionService;
use App\Modules\Ai\Application\Capabilities\LexemeTranslationService;
use App\Modules\Ai\Application\Capabilities\SentenceAnswerGradingService;
use App\Modules\Ai\Application\Capabilities\SentenceGenerationService;
use App\Modules\Ai\Application\Capabilities\TextTranslationService;
use App\Modules\Ai\Application\ChatContextAiService;
use App\Modules\Ai\Application\GrammarRuleEmbeddingMergeParticipant;
use App\Modules\Ai\Application\InterviewConversationService;
use App\Modules\Ai\Application\LessonAssistantService;
use App\Modules\Ai\Application\LexemeEnrichmentService;
use App\Modules\Ai\Application\ManualLexemeCandidateService;
use App\Modules\Ai\Application\PromptRegistryService;
use App\Modules\Ai\Application\QueuedGrammarExerciseGenerationDispatcher;
use App\Modules\Ai\Application\QueuedGrammarRuleExampleGenerationDispatcher;
use App\Modules\Ai\Application\QueuedLexemeEnrichmentDispatcher;
use App\Modules\Ai\Application\SentencePracticeService;
use App\Modules\Ai\Infrastructure\AiProviderFactory;
use App\Modules\Ai\Infrastructure\ElasticsearchClient;
use App\Modules\Ai\Infrastructure\OllamaClient;
use App\Modules\Ai\Infrastructure\OpenAiClient;
use App\Modules\Ai\Infrastructure\OpenAiEmbeddingsClient;
use App\Modules\Content\Application\Contracts\GrammarRuleMergeParticipant;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(InterviewConversationGateway::class, InterviewConversationService::class);
        $this->app->bind(LessonAssistant::class, LessonAssistantService::class);
        $this->app->tag([GrammarRuleEmbeddingMergeParticipant::class], GrammarRuleMergeParticipant::TAG);
        $this->app->singleton(AiProviderFactory::class, fn () => new AiProviderFactory(
            provider: (string) config('ai.provider', 'openai'),
            openAiApiKey: (string) (config('ai.openai.api_key') ?? ''),
            ollamaUrl: (string) config('ai.ollama.url', 'http://localhost:11434'),
            ollamaModel: (string) config('ai.ollama.model', 'llama3.2'),
            timeout: (int) config('ai.timeout', 30),
            embeddingsModel: (string) config('ai.embeddings.model', 'text-embedding-3-small'),
            embeddingsProvider: (string) config('ai.embeddings.provider', 'openai'),
        ));

        $this->app->bind(AiClientInterface::class, fn ($app) => $app->make(AiProviderFactory::class)->makeChatClient());

        // AiJsonClient is implemented by the same provider classes as AiClientInterface
        // (OpenAiClient, OllamaClient) and switches on the same config('ai.provider') —
        // do not add a second, separate provider-selection mechanism for JSON mode.
        $this->app->bind(AiJsonClient::class, fn ($app) => $app->make(AiProviderFactory::class)->makeChatClient());

        // Tool calling is OpenAI-only today (see AiToolCallingClient docblock).
        $this->app->bind(AiToolCallingClient::class, function ($app) {
            $client = $app->make(AiProviderFactory::class)->makeChatClient();

            if (! $client instanceof AiToolCallingClient) {
                throw new \RuntimeException(
                    'The agent chat feature requires ai.provider=openai; the configured provider does not support tool calling.'
                );
            }

            return $client;
        });

        // Streaming tool calling (task 3.6) — same OpenAI-only constraint as
        // AiToolCallingClient above, see AiStreamingChatClient docblock.
        $this->app->bind(AiStreamingChatClient::class, function ($app) {
            $client = $app->make(AiProviderFactory::class)->makeChatClient();

            if (! $client instanceof AiStreamingChatClient) {
                throw new \RuntimeException(
                    'Streaming TutorAgent replies require ai.provider=openai; the configured provider does not support streaming.'
                );
            }

            return $client;
        });

        $this->app->bind(ChatAiServiceInterface::class, ChatContextAiService::class);

        $this->app->bind(PromptRegistryInterface::class, PromptRegistryService::class);
        $this->app->bind(ContentAnalysisCapability::class, AiContentAnalysisService::class);
        $this->app->bind(AiFieldEditCapability::class, AiFieldEditService::class);
        $this->app->bind(ContentExamGenerationCapability::class, SentencePracticeService::class);
        $this->app->bind(AiAnalysisRunDispatcher::class, AiAnalysisRunDispatchService::class);
        $this->app->bind(LexemeExplanationCapability::class, LexemeExplanationService::class);
        $this->app->bind(TextTranslationCapability::class, TextTranslationService::class);
        $this->app->bind(LexemeEnrichmentCapability::class, LexemeEnrichmentService::class);
        $this->app->bind(LexemeEnrichmentDispatcher::class, QueuedLexemeEnrichmentDispatcher::class);
        $this->app->bind(GrammarRuleExampleGenerationDispatcher::class, QueuedGrammarRuleExampleGenerationDispatcher::class);

        $this->app->bind(GrammarExerciseGenerationDispatcher::class, QueuedGrammarExerciseGenerationDispatcher::class);
        $this->app->bind(LexemeMetadataSuggestionCapability::class, LexemeMetadataSuggestionService::class);
        $this->app->bind(ContextSentenceGenerationCapability::class, ContextSentenceGenerationService::class);
        $this->app->bind(LexemeTranslationCapability::class, LexemeTranslationService::class);
        $this->app->bind(ManualLexemeCandidateCapability::class, ManualLexemeCandidateService::class);
        $this->app->bind(SentenceGenerationCapability::class, SentenceGenerationService::class);
        $this->app->bind(SentenceAnswerGradingCapability::class, SentenceAnswerGradingService::class);

        // Tier 1 tracing: no-op by default (same pattern as NullKafkaProducer
        // in AppServiceProvider) — opt into persisting to agent_trace_spans
        // via ai.tracing.enabled (off by default, same as ai.agent.enabled).
        $this->app->bind(SpanRecorder::class, function ($app) {
            return config('ai.tracing.enabled', false)
                ? $app->make(DatabaseSpanRecorder::class)
                : new NullSpanRecorder;
        });

        $this->app->bind(EmbeddingsClientInterface::class, function ($app) {
            return $app->make(AiProviderFactory::class)->makeEmbeddingsClient();
        });

        // Elasticsearch client for the RAG corpus (task 2.2-2.4). Bound as a
        // singleton the same way OpenAiEmbeddingsClient is configured from
        // config() rather than env() directly — one place reads env, and
        // RagIndexingService/RagRetrievalService just depend on the client
        // type, they don't know how it was built. See ElasticsearchClient's
        // docblock for why this is a thin Http-facade wrapper and not the
        // official elasticsearch/elasticsearch package.
        $this->app->singleton(ElasticsearchClient::class, function ($app) {
            return new ElasticsearchClient(config('elasticsearch.hosts')[0]);
        });

        // Task 4.10: explicit node_key => FQCN map, read once here — the
        // one place that knows graph node registration exists, same as
        // ai.agent.registry above.
        // ~5 minutes max per stream connection (600 * 500ms) — see
        // GraphRunStatusStreamer's docblock for why this is a poll, not a
        // new broadcast layer.
        $this->app->bind(GraphRunStatusStreamer::class, fn () => new GraphRunStatusStreamer(600, 500000));

        $this->app->singleton(GraphNodeRegistry::class, function ($app) {
            return new GraphNodeRegistry($app, config('ai.graph.node_registry', []));
        });

        // Explicit tool list per agent, not auto-discovery — every agent must
        // only ever be able to call a known, reviewed set of tools. Both
        // agents share the same wiring shape (AgentLoop + AgentBlueprint +
        // resolved tools + SpanRecorder), so bindAgentService() below is the
        // one place that resolves blueprint()->tools through the container
        // and enforces the sideEffect invariant — proof that a second agent
        // did not require duplicating this logic (task 1.10).
        $this->bindAgentService(ContentAgentService::class);
        $this->bindAgentService(StudentTutorAgentService::class);

        // Third coordinator (task: "Мои занятия" / Lesson capture) — wired
        // through the exact same bindAgentService() as the two above, no
        // special-casing needed (ADR-001, see LessonAgentService's docblock
        // for why this is its own agent and not new tools bolted onto
        // StudentTutorAgentService).
        $this->bindAgentService(LessonAgentService::class);
        $this->bindAgentService(InterviewAgentService::class);

        // GrammarAgentService (task 4.4) is a specialist agent, not a third
        // coordinator (ADR-001) — it has no AgentConversation/HTTP endpoint
        // of its own, but it is wired through the exact same
        // bindAgentService() as the two coordinators because its
        // constructor shape and sideEffect-at-wiring-time requirement are
        // identical; only how it's invoked (run(), not handleTurn()) differs.
        $this->bindAgentService(GrammarAgentService::class);
        $this->bindAgentService(ReviewAgentService::class);

        // Task 4.3/4.4/4.6: what actually makes GrammarAgentService/
        // ReviewAgentService reachable from StudentTutorAgentService's own
        // tool-calling loop (model-directed handoff, ai-platform-vision.md
        // section 6 "Mode B") — StudentTutorAgentService::blueprint() lists
        // these two tool classes.
        $this->bindHandoffTool(HandoffToGrammarAgentTool::class, GrammarAgentService::class);
        $this->bindHandoffTool(HandoffToReviewAgentTool::class, ReviewAgentService::class);
    }

    /**
     * @param  class-string<ContentAgentService|StudentTutorAgentService|GrammarAgentService|ReviewAgentService|LessonAgentService>  $serviceClass  Must expose a static blueprint(): AgentBlueprint and accept (AgentLoop, AgentBlueprint, array $tools, SpanRecorder) in its constructor.
     */
    private function bindAgentService(string $serviceClass): void
    {
        $this->app->bind($serviceClass, function ($app) use ($serviceClass) {
            /** @var AgentBlueprint $blueprint */
            $blueprint = $this->resolveBlueprintSystemPrompt($app, $serviceClass::blueprint());

            $tools = array_map(fn (string $toolClass) => $app->make($toolClass), $blueprint->tools);

            // sideEffect is an invariant enforced here, not a convention in a
            // docblock: an agent must never be handed a tool whose sideEffect
            // exceeds what its blueprint allows — see
            // docs/architecture/agent-framework-roadmap.md, step 5.3. A tool
            // with a disallowed sideEffect throws as soon as this binding is
            // resolved, before the agent can run at all.
            AgentToolDefinition::assertSideEffectsAllowed(
                array_map(fn (AgentTool $tool) => $tool->definition(), $tools),
                $blueprint->allowedSideEffects
            );

            return new $serviceClass($app->make(AgentLoop::class), $blueprint, $tools, $app->make(SpanRecorder::class));
        });
    }

    /**
     * Builds a `HandoffTool` subclass wired to a specific specialist
     * agent's blueprint/tools — the same "resolve tools through the
     * container, blueprint drives the sideEffect check" shape as
     * `bindAgentService()` above, except the sideEffect check here is
     * `HandoffTool::assertSideEffectCoversTarget()` (constructor-time,
     * ADR-006), not `assertSideEffectsAllowed()`.
     *
     * @param  class-string<HandoffTool>  $handoffToolClass
     * @param  class-string<GrammarAgentService|ReviewAgentService>  $targetServiceClass  Must expose a static blueprint(): AgentBlueprint.
     */
    private function bindHandoffTool(string $handoffToolClass, string $targetServiceClass): void
    {
        $this->app->bind($handoffToolClass, function ($app) use ($handoffToolClass, $targetServiceClass) {
            /** @var AgentBlueprint $targetBlueprint */
            $targetBlueprint = $this->resolveBlueprintSystemPrompt($app, $targetServiceClass::blueprint());
            $targetTools = array_map(fn (string $toolClass) => $app->make($toolClass), $targetBlueprint->tools);

            return new $handoffToolClass($app->make(AgentLoop::class), $targetBlueprint, $targetTools, $app->make(SpanRecorder::class));
        });
    }

    public function boot(): void
    {
        Route::middleware('api')
            ->prefix('api')
            ->group(app_path('Modules/Ai/Routes/api.php'));
    }

    /**
     * Lets an admin override an agent's system prompt from the prompt
     * registry (task: graph/agent builder groundwork) without touching
     * `tools`, `maxIterations`, or `allowedSideEffects` — those stay
     * exactly as `$blueprint::blueprint()` declared them. Deliberately
     * narrow: the closed, reviewed set of tools an agent may call and the
     * sideEffect ceiling `assertSideEffectsAllowed()` enforces right after
     * this method returns are a security boundary, not a copy-editing
     * concern — only the wording of the prompt is admin-editable, the same
     * "compose/configure existing reviewed building blocks, never grant
     * new capability from the UI" boundary the graph-builder canvas keeps
     * for nodes. The prompt key (`agent_{name}_system_prompt`) is derived
     * from `$blueprint->name`, which is also `AgentConversation::agent_type`
     * and the `config('ai.agent.registry')` key — one stable identifier,
     * not a second name to keep in sync.
     */
    private function resolveBlueprintSystemPrompt($app, AgentBlueprint $blueprint): AgentBlueprint
    {
        $rendered = $app->make(PromptRegistryInterface::class)->resolve(
            "agent_{$blueprint->name}_system_prompt",
            [],
            fn () => ['system' => $blueprint->systemPrompt, 'user' => '']
        );

        if (! $rendered->isOverride) {
            return $blueprint;
        }

        return new AgentBlueprint(
            name: $blueprint->name,
            systemPrompt: $rendered->system,
            tools: $blueprint->tools,
            maxIterations: $blueprint->maxIterations,
            allowedSideEffects: $blueprint->allowedSideEffects,
        );
    }
}
