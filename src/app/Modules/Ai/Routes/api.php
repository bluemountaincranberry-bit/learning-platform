<?php

use App\Modules\Ai\Interfaces\Http\Controllers\Admin\AgentListController;
use App\Modules\Ai\Interfaces\Http\Controllers\Admin\GraphDefinitionController;
use App\Modules\Ai\Interfaces\Http\Controllers\Admin\GraphRunStreamController;
use App\Modules\Ai\Interfaces\Http\Controllers\Admin\NodePaletteController;
use App\Modules\Ai\Interfaces\Http\Controllers\Admin\PromptCatalogController;
use App\Modules\Ai\Interfaces\Http\Controllers\Admin\PromptTemplateController;
use App\Modules\Ai\Interfaces\Http\Controllers\AiConversationController;
use App\Modules\Ai\Interfaces\Http\Controllers\RecommendedController;
use App\Modules\Ai\Interfaces\Http\Controllers\SentencePracticeController;
use App\Modules\Ai\Interfaces\Http\Controllers\TranslateController;
use App\Modules\Ai\Interfaces\Http\Controllers\TutorConversationController;
use App\Modules\Ai\Interfaces\Http\Controllers\VoiceRecordingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function (): void {
    Route::get('/ai/voice-recordings/{message}/audio', [VoiceRecordingController::class, 'stream'])->name('api.ai.voice-recordings.audio');
    Route::post('/ai/voice-recordings/{message}/pin', [VoiceRecordingController::class, 'pin'])->name('api.ai.voice-recordings.pin');
    Route::get('/ai/recommended/contents', [RecommendedController::class, 'contents'])->name('api.ai.recommended.contents');
    Route::get('/ai/recommended/lexemes', [RecommendedController::class, 'lexemes'])->name('api.ai.recommended.lexemes');
    Route::post('/ai/translate', TranslateController::class)
        ->middleware('ai.rate_limit:explain')
        ->name('api.ai.translate');
    Route::post('/ai/conversations', [AiConversationController::class, 'store'])->name('api.ai.conversations.store');
    Route::post('/ai/conversations/{conversation}/messages', [AiConversationController::class, 'storeMessage'])
        ->middleware('ai.rate_limit:chat')
        ->name('api.ai.conversations.messages.store');

    // TutorAgent (EPIC-3.5): access control lives here at the route
    // boundary (task 3.8), not spread across StudentTutorAgentService's
    // tools — see the access-tutor-agent gate in AppServiceProvider.
    Route::middleware('can:access-tutor-agent')->group(function (): void {
        Route::post('/tutor/conversations', [TutorConversationController::class, 'store'])
            ->name('api.tutor.conversations.store');
        Route::post('/tutor/conversations/{conversation}/messages', [TutorConversationController::class, 'storeMessage'])
            ->name('api.tutor.conversations.messages.store');
    });

    // Sentence practice is a learner-facing exercise available from the
    // shared content practice menu. It must also work for staff accounts
    // while they preview/test the learner flow, so it intentionally does not
    // use the TutorAgent audience gate. The dedicated AI rate limit still
    // protects both LLM-backed endpoints.
    Route::middleware('ai.rate_limit:sentence_practice')->group(function (): void {
        Route::post('/practice/sentences/start', [SentencePracticeController::class, 'start'])->name('api.practice.sentences.start');
        Route::post('/practice/sentences/check', [SentencePracticeController::class, 'check'])->name('api.practice.sentences.check');
    });

    // Prompt/graph builder (graph-builder groundwork): admin-only, gated by
    // manage-ai-builder (AppServiceProvider) — editing a prompt or a
    // graph's wiring changes what every learner-facing AI call does, kept
    // stricter than manage-content (admin/editor).
    Route::middleware('can:manage-ai-builder')->prefix('admin/ai-builder')->group(function (): void {
        Route::get('/node-palette', [NodePaletteController::class, 'index'])->name('api.admin.ai-builder.node-palette');
        Route::get('/agents', [AgentListController::class, 'index'])->name('api.admin.ai-builder.agents');
        Route::get('/prompt-catalog', [PromptCatalogController::class, 'index'])->name('api.admin.ai-builder.prompt-catalog');

        Route::get('/prompt-templates', [PromptTemplateController::class, 'index'])->name('api.admin.ai-builder.prompt-templates.index');
        Route::get('/prompt-templates/{key}', [PromptTemplateController::class, 'show'])->name('api.admin.ai-builder.prompt-templates.show');
        Route::post('/prompt-templates/{key}/versions', [PromptTemplateController::class, 'store'])->name('api.admin.ai-builder.prompt-templates.store');
        Route::post('/prompt-templates/{key}/versions/{versionId}/publish', [PromptTemplateController::class, 'publish'])->name('api.admin.ai-builder.prompt-templates.publish');
        Route::post('/prompt-templates/{key}/test', [PromptTemplateController::class, 'testRun'])->name('api.admin.ai-builder.prompt-templates.test');
        Route::post('/prompt-templates/{key}/chat', [PromptTemplateController::class, 'chat'])->name('api.admin.ai-builder.prompt-templates.chat');

        Route::get('/graph-definitions', [GraphDefinitionController::class, 'index'])->name('api.admin.ai-builder.graph-definitions.index');
        Route::get('/graph-definitions/{key}', [GraphDefinitionController::class, 'show'])->name('api.admin.ai-builder.graph-definitions.show');
        Route::post('/graph-definitions/{key}/versions', [GraphDefinitionController::class, 'store'])->name('api.admin.ai-builder.graph-definitions.store');
        Route::post('/graph-definitions/{key}/versions/{versionId}/publish', [GraphDefinitionController::class, 'publish'])->name('api.admin.ai-builder.graph-definitions.publish');
        Route::post('/graph-definitions/{key}/versions/{versionId}/test', [GraphDefinitionController::class, 'testRun'])->name('api.admin.ai-builder.graph-definitions.test');

        Route::get('/graph-runs/{run}/stream', [GraphRunStreamController::class, 'stream'])->name('api.admin.ai-builder.graph-runs.stream');
    });
});
