<?php

use App\Modules\Learning\Interfaces\Http\Controllers\LearnedGrammarRulesController;
use App\Modules\Learning\Interfaces\Http\Controllers\LearnedLexemesController;
use App\Modules\Learning\Interfaces\Http\Controllers\MyWordsController;
use App\Modules\Learning\Interfaces\Http\Controllers\ProgressStatsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function (): void {
    Route::get('/me/words', [MyWordsController::class, 'index'])->name('api.me.words');
    Route::get('/me/learned-lexemes', [LearnedLexemesController::class, 'index'])->name('api.me.learned-lexemes');
    Route::get('/me/grammar-rules', [LearnedGrammarRulesController::class, 'index'])->name('api.me.grammar-rules');
    Route::get('/me/stats', [ProgressStatsController::class, 'show'])->name('api.me.stats');
});
