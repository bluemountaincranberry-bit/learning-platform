<?php

use App\Modules\Ai\Application\Agent\ContentAgentService;
use App\Modules\Ai\Application\Agent\StudentTutorAgentService;
use App\Modules\Ai\Application\AiContentAnalysisService;
use App\Modules\Ai\Application\Capabilities\ContextSentenceGenerationService;
use App\Modules\Ai\Application\Capabilities\LexemeExplanationService;
use App\Modules\Ai\Application\Capabilities\LexemeMetadataSuggestionService;
use App\Modules\Ai\Application\Capabilities\LexemeTranslationService;
use App\Modules\Ai\Application\Capabilities\SentenceAnswerGradingService;
use App\Modules\Ai\Application\Capabilities\SentenceGenerationService;
use App\Contracts\Ai\AiClientInterface;
use App\Contracts\Ai\AiJsonClient;
use App\Contracts\Ai\AiToolCallingClient;
use App\Contracts\Ai\ContentAnalysisCapability;
use App\Contracts\Ai\ContextSentenceGenerationCapability;
use App\Contracts\Ai\LexemeExplanationCapability;
use App\Contracts\Ai\LexemeMetadataSuggestionCapability;
use App\Contracts\Ai\LexemeTranslationCapability;
use App\Contracts\Ai\SentenceAnswerGradingCapability;
use App\Contracts\Ai\SentenceGenerationCapability;
use App\Modules\Ai\Infrastructure\AiProviderFactory;
use App\Modules\Ai\Infrastructure\OllamaClient;
use App\Modules\Ai\Infrastructure\OllamaEmbeddingsClient;
use App\Modules\Ai\Infrastructure\OpenAiClient;
use App\Modules\Ai\Infrastructure\OpenAiEmbeddingsClient;

test('AiClientInterface and AiJsonClient resolve to the same provider class for openai', function () {
    config(['ai.provider' => 'openai']);

    expect(app(AiClientInterface::class))->toBeInstanceOf(OpenAiClient::class)
        ->and(app(AiJsonClient::class))->toBeInstanceOf(OpenAiClient::class);
});

test('AiClientInterface and AiJsonClient resolve to the same provider class for ollama', function () {
    config(['ai.provider' => 'ollama']);

    expect(app(AiClientInterface::class))->toBeInstanceOf(OllamaClient::class)
        ->and(app(AiJsonClient::class))->toBeInstanceOf(OllamaClient::class);
});

test('provider factory is the single construction boundary for chat and embeddings clients', function () {
    config([
        'ai.provider' => 'openai',
        'ai.openai.api_key' => 'test-key',
        'ai.embeddings.model' => 'test-embedding-model',
    ]);

    $factory = app(AiProviderFactory::class);

    expect($factory->makeChatClient())->toBeInstanceOf(OpenAiClient::class)
        ->and($factory->makeEmbeddingsClient())->toBeInstanceOf(OpenAiEmbeddingsClient::class);
});

test('embeddings provider can be routed independently to Ollama', function () {
    config([
        'ai.embeddings.provider' => 'ollama',
        'ai.embeddings.model' => 'nomic-embed-text',
        'ai.ollama.url' => 'http://ollama.test',
    ]);

    expect(app(AiProviderFactory::class)->makeEmbeddingsClient())
        ->toBeInstanceOf(OllamaEmbeddingsClient::class);
});

test('AI capabilities resolve through provider-independent contracts', function () {
    expect(app(LexemeExplanationCapability::class))->toBeInstanceOf(LexemeExplanationService::class)
        ->and(app(LexemeMetadataSuggestionCapability::class))->toBeInstanceOf(LexemeMetadataSuggestionService::class)
        ->and(app(ContextSentenceGenerationCapability::class))->toBeInstanceOf(ContextSentenceGenerationService::class)
        ->and(app(LexemeTranslationCapability::class))->toBeInstanceOf(LexemeTranslationService::class)
        ->and(app(SentenceGenerationCapability::class))->toBeInstanceOf(SentenceGenerationService::class)
        ->and(app(SentenceAnswerGradingCapability::class))->toBeInstanceOf(SentenceAnswerGradingService::class);
});

test('content analysis resolves through its provider-independent contract', function () {
    expect(app(ContentAnalysisCapability::class))->toBeInstanceOf(AiContentAnalysisService::class);
});

test('ContentAgentService wiring resolves — its real tools all pass the sideEffect wiring-time check', function () {
    app()->instance(AiToolCallingClient::class, Mockery::mock(AiToolCallingClient::class));

    expect(app(ContentAgentService::class))->toBeInstanceOf(ContentAgentService::class);
});

test('StudentTutorAgentService wiring resolves — proof the runtime supports a second agent (task 1.10)', function () {
    app()->instance(AiToolCallingClient::class, Mockery::mock(AiToolCallingClient::class));

    expect(app(StudentTutorAgentService::class))->toBeInstanceOf(StudentTutorAgentService::class);
});

test('both agents are registered in ai.agent.registry under their own agent_type', function () {
    $registry = config('ai.agent.registry');

    expect($registry[ContentAgentService::AGENT_TYPE])->toBe(ContentAgentService::class)
        ->and($registry[StudentTutorAgentService::AGENT_TYPE])->toBe(StudentTutorAgentService::class);
});
