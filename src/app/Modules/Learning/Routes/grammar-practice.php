<?php

use App\Modules\Learning\Interfaces\Http\Controllers\GrammarPracticeController;
use Illuminate\Support\Facades\Route;

// Grammar practice (VIK-31). No route-level AI limit: only startRound may
// queue AI generation, and that is limited per rule and learner per day
// inside GrammarExerciseGenerations.
Route::middleware(['auth:sanctum', 'throttle:120,1'])->group(function (): void {
    Route::get('/grammar-rules/{rule}/practice', [GrammarPracticeController::class, 'show'])
        ->whereNumber('rule')->name('api.grammar-practice.show');
    Route::post('/grammar-rules/{rule}/practice/rounds', [GrammarPracticeController::class, 'startRound'])
        ->whereNumber('rule')->name('api.grammar-practice.rounds');
    Route::post('/grammar-rules/{rule}/practice/complete', [GrammarPracticeController::class, 'complete'])
        ->whereNumber('rule')->name('api.grammar-practice.complete');
    Route::post('/grammar-exercises/{exercise}/check', [GrammarPracticeController::class, 'check'])
        ->whereNumber('exercise')->name('api.grammar-practice.check');
    Route::post('/grammar-exercises/{exercise}/report', [GrammarPracticeController::class, 'report'])
        ->whereNumber('exercise')->name('api.grammar-practice.report');
});
