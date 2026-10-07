<?php

use App\Modules\Learning\Interfaces\Http\Controllers\LearnedGrammarRulesController;
use App\Modules\Learning\Interfaces\Http\Controllers\LearnedLexemesController;
use App\Modules\Learning\Interfaces\Http\Controllers\MyWordsController;
use App\Modules\Learning\Interfaces\Http\Controllers\AddPersonalWordController;
use App\Modules\Learning\Interfaces\Http\Controllers\PersonalWordLearningController;
use App\Modules\Learning\Interfaces\Http\Controllers\PersonalWordPracticeController;
use App\Modules\Learning\Interfaces\Http\Controllers\ProgressStatsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function (): void {
    Route::get('/me/words', [MyWordsController::class, 'index'])->name('api.me.words');
    Route::post('/me/words', AddPersonalWordController::class)->name('api.me.words.add');
    Route::post('/me/words/{lexeme}/start-learning', [PersonalWordLearningController::class, 'start'])->name('api.me.words.start-learning');
    Route::post('/me/words/{lexeme}/stop-learning', [PersonalWordLearningController::class, 'stop'])->name('api.me.words.stop-learning');
    Route::post('/me/words/{lexeme}/mark-known', [PersonalWordLearningController::class, 'markKnown'])->name('api.me.words.mark-known');
    Route::delete('/me/words/{lexeme}/mark-known', [PersonalWordLearningController::class, 'unmarkKnown'])->name('api.me.words.unmark-known');
    Route::post('/me/words/{lexeme}/practice', PersonalWordPracticeController::class)->whereNumber('lexeme')->name('api.me.words.practice');
    Route::get('/me/learned-lexemes', [LearnedLexemesController::class, 'index'])->name('api.me.learned-lexemes');
    Route::get('/me/grammar-rules', [LearnedGrammarRulesController::class, 'index'])->name('api.me.grammar-rules');
    Route::get('/me/stats', [ProgressStatsController::class, 'show'])->name('api.me.stats');
});
