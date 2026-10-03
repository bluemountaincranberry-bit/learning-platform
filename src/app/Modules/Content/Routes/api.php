<?php

use App\Modules\Content\Interfaces\Http\Controllers\AdminGrammarCoverageController;
use App\Modules\Content\Interfaces\Http\Controllers\AdminGrammarRuleController;
use App\Modules\Content\Interfaces\Http\Controllers\AdminGrammarTopicController;
use App\Modules\Content\Interfaces\Http\Controllers\AdminLexemeController;
use App\Modules\Content\Interfaces\Http\Controllers\ContentAiSuggestionsController;
use App\Modules\Content\Interfaces\Http\Controllers\ContentController;
use App\Modules\Content\Interfaces\Http\Controllers\TranscriptController;
use App\Modules\Content\Interfaces\Http\Controllers\TranscriptTranslationController;
use App\Modules\Content\Interfaces\Http\Controllers\ContentGrammarPreExamController;
use App\Modules\Content\Interfaces\Http\Controllers\ContentReadinessController;
use App\Modules\Content\Interfaces\Http\Controllers\GrammarRuleController;
use App\Modules\Content\Interfaces\Http\Controllers\LexemeController;
use Illuminate\Support\Facades\Route;

Route::get('/content/categories', [ContentController::class, 'categories'])->name('api.content.categories');
Route::get('/content', [ContentController::class, 'index'])->name('api.content.index');
Route::get('/content/my-submissions', [ContentController::class, 'mySubmissions'])
    ->middleware('auth:sanctum')
    ->name('api.content.my-submissions');
Route::get('/content/{content}', [ContentController::class, 'show'])->name('api.content.show');
Route::get('/content/{content}/transcript', [TranscriptController::class, 'index'])->name('api.content.transcript');
Route::get('/content/{content}/grammar-rules', [GrammarRuleController::class, 'forContent'])->name('api.content.grammar-rules');

Route::get('/grammar-rules', [GrammarRuleController::class, 'index'])->name('api.grammar-rules.index');
Route::get('/grammar-rules/{rule}', [GrammarRuleController::class, 'show'])->name('api.grammar-rules.show');
Route::get('/grammar-rules/{rule}/exercises', [GrammarRuleController::class, 'exercises'])->name('api.grammar-rules.exercises');

Route::get('/dictionary/{word}', [LexemeController::class, 'show'])->name('api.dictionary.show');

Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function (): void {
    Route::post('/content/submit-youtube', [ContentController::class, 'submitYoutube'])->name('api.content.submit-youtube');
    Route::post('/content/import-youtube-transcript', [ContentController::class, 'importYoutubeTranscript'])->name('api.content.import-youtube-transcript');
    Route::get('/content/{content}/transcript/translations', [TranscriptTranslationController::class, 'index'])
        ->middleware('ai.rate_limit:explain')
        ->name('api.content.transcript.translations');
    Route::post('/content/{content}/transcript/lexemes', [TranscriptController::class, 'createLexeme'])
        ->middleware('ai.rate_limit:explain')
        ->name('api.content.transcript.lexemes.create');
    Route::get('/content/{content}/lexemes', [ContentController::class, 'lexemes'])->name('api.content.lexemes');
    Route::post('/content/lexemes/{lexeme}/mark-learned', [LexemeController::class, 'markLearned'])->name('api.lexemes.mark-learned');
    Route::post('/content/lexemes/{lexeme}/unmark-learned', [LexemeController::class, 'unmarkLearned'])->name('api.lexemes.unmark-learned');
    Route::post('/content/lexemes/{lexeme}/start-learning', [LexemeController::class, 'startLearning'])->name('api.lexemes.start-learning');
    Route::post('/content/lexemes/{lexeme}/stop-learning', [LexemeController::class, 'stopLearning'])->name('api.lexemes.stop-learning');
    Route::post('/content/lexemes/{lexeme}/skip', [LexemeController::class, 'skip'])->name('api.lexemes.skip');
    Route::post('/content/lexemes/{lexeme}/unskip', [LexemeController::class, 'unskip'])->name('api.lexemes.unskip');
    // Bulk counterparts (task 7.5) — a fixed 'bulk-*' segment, distinct from
    // the numeric {lexeme} segment above, so route matching never confuses
    // the two shapes.
    Route::post('/content/lexemes/bulk-mark-learned', [LexemeController::class, 'bulkMarkLearned'])->name('api.lexemes.bulk-mark-learned');
    Route::post('/content/lexemes/bulk-start-learning', [LexemeController::class, 'bulkStartLearning'])->name('api.lexemes.bulk-start-learning');
    Route::post('/lexemes/{lexeme}/explain', [LexemeController::class, 'explain'])
        ->middleware('ai.rate_limit:explain')
        ->name('api.lexemes.explain');
    Route::post('/lexemes/{lexeme}/generate-sentence', [LexemeController::class, 'generateSentence'])
        ->middleware('ai.rate_limit:explain')
        ->name('api.lexemes.generate-sentence');
    Route::post('/cloze-examples/prepare', [LexemeController::class, 'prepareClozeExamples'])
        ->middleware('ai.rate_limit:sentence_practice')
        ->name('api.cloze-examples.prepare');
    Route::post('/dictionary/{word}/more-examples', [LexemeController::class, 'moreExamples'])
        ->middleware('ai.rate_limit:explain')
        ->name('api.dictionary.more-examples');

    Route::post('/grammar-rules/{rule}/start-learning', [GrammarRuleController::class, 'startLearning'])->name('api.grammar-rules.start-learning');
    Route::post('/grammar-rules/{rule}/mark-learned', [GrammarRuleController::class, 'markLearned'])->name('api.grammar-rules.mark-learned');
    Route::post('/grammar-rules/{rule}/unmark-learned', [GrammarRuleController::class, 'unmarkLearned'])->name('api.grammar-rules.unmark-learned');
    Route::post('/grammar-rules/{rule}/confidence', [GrammarRuleController::class, 'setConfidence'])->name('api.grammar-rules.confidence');

    // EPIC 9 (tasks 9.2/9.3): review/accept AI candidates, user-facing (the
    // content's own submitter — ContentPolicy::reviewAiSuggestions()), not
    // the Filament admin-only path.
    Route::get('/content/{content}/ai-suggestions', [ContentAiSuggestionsController::class, 'index'])->name('api.content.ai-suggestions.index');
    Route::post('/content/{content}/ai-suggestions/accept', [ContentAiSuggestionsController::class, 'accept'])->name('api.content.ai-suggestions.accept');
    Route::post('/content/{content}/reanalyze', [ContentAiSuggestionsController::class, 'reanalyze'])->name('api.content.reanalyze');

    // "Ready to watch" quest: query derived status and record exam attempts.
    // completion are plain reads/writes (no LLM call), open to any learner
    // for any content — only exam/start calls the AI, so only that one
    // carries the AI rate limit.
    Route::get('/content/{content}/readiness', [ContentReadinessController::class, 'show'])->name('api.content.readiness.show');
    Route::post('/content/{content}/readiness/exam/start', [ContentReadinessController::class, 'examStart'])
        ->middleware('ai.rate_limit:sentence_practice')
        ->name('api.content.readiness.exam.start');
    Route::post('/content/{content}/readiness/exam/complete', [ContentReadinessController::class, 'examComplete'])
        ->name('api.content.readiness.exam.complete');

    // Grammar warm-up (ContentGrammarPreExamController): pre/post diagnostic
    // scoped to whichever of the content's grammar rules the learner picks
    // — unlike readiness/exam above, never gated on "everything already
    // learned". Only start() calls the AI, so only that route carries the
    // AI rate limit.
    Route::post('/content/{content}/grammar-warmup/start', [ContentGrammarPreExamController::class, 'start'])
        ->middleware('ai.rate_limit:sentence_practice')
        ->name('api.content.grammar-warmup.start');
    Route::post('/content/{content}/grammar-warmup/complete', [ContentGrammarPreExamController::class, 'complete'])
        ->name('api.content.grammar-warmup.complete');
});

Route::middleware(['auth:sanctum', 'can:manage-content', 'throttle:60,1'])
    ->prefix('/admin/grammar')
    ->group(function (): void {
        Route::get('/topics', [AdminGrammarTopicController::class, 'index'])->name('api.admin.grammar.topics.index');
        Route::post('/topics', [AdminGrammarTopicController::class, 'store'])->name('api.admin.grammar.topics.store');
        Route::get('/topics/{topic}', [AdminGrammarTopicController::class, 'show'])->name('api.admin.grammar.topics.show');
        Route::match(['put', 'patch'], '/topics/{topic}', [AdminGrammarTopicController::class, 'update'])->name('api.admin.grammar.topics.update');
        Route::delete('/topics/{topic}', [AdminGrammarTopicController::class, 'destroy'])->name('api.admin.grammar.topics.destroy');

        Route::get('/rules', [AdminGrammarRuleController::class, 'index'])->name('api.admin.grammar.rules.index');
        Route::post('/rules', [AdminGrammarRuleController::class, 'store'])->name('api.admin.grammar.rules.store');
        Route::get('/rules/{rule}', [AdminGrammarRuleController::class, 'show'])->name('api.admin.grammar.rules.show');
        Route::match(['put', 'patch'], '/rules/{rule}', [AdminGrammarRuleController::class, 'update'])->name('api.admin.grammar.rules.update');
        Route::delete('/rules/{rule}', [AdminGrammarRuleController::class, 'destroy'])->name('api.admin.grammar.rules.destroy');

        Route::get('/lexemes', [AdminLexemeController::class, 'index'])->name('api.admin.grammar.lexemes.index');
        Route::post('/lexemes', [AdminLexemeController::class, 'store'])->name('api.admin.grammar.lexemes.store');
        Route::get('/lexemes/{lexeme}', [AdminLexemeController::class, 'show'])->name('api.admin.grammar.lexemes.show');
        Route::match(['put', 'patch'], '/lexemes/{lexeme}', [AdminLexemeController::class, 'update'])->name('api.admin.grammar.lexemes.update');
        Route::delete('/lexemes/{lexeme}', [AdminLexemeController::class, 'destroy'])->name('api.admin.grammar.lexemes.destroy');

        Route::get('/coverage', AdminGrammarCoverageController::class)->name('api.admin.grammar.coverage');
    });
