<?php

use App\Modules\Learning\Interfaces\Http\Controllers\LessonController;
use App\Modules\Learning\Interfaces\Http\Controllers\LessonItemController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function (): void {
    Route::get('/lessons', [LessonController::class, 'index'])->name('api.lessons.index');
    Route::post('/lessons', [LessonController::class, 'store'])->name('api.lessons.store');
    Route::get('/lessons/{lesson}', [LessonController::class, 'show'])->name('api.lessons.show');
    Route::put('/lessons/{lesson}', [LessonController::class, 'update'])->name('api.lessons.update');
    Route::delete('/lessons/{lesson}', [LessonController::class, 'destroy'])->name('api.lessons.destroy');
    Route::post('/lessons/{lesson}/restore', [LessonController::class, 'restore'])->name('api.lessons.restore');
    Route::get('/lessons/{lesson}/messages', [LessonController::class, 'messages'])->name('api.lessons.messages.index');
    Route::post('/lessons/{lesson}/messages', [LessonController::class, 'storeMessage'])->name('api.lessons.messages.store');
    Route::post('/lessons/{lesson}/analyze', [LessonController::class, 'analyze'])->name('api.lessons.analyze');

    Route::post('/lessons/{lesson}/lexemes', [LessonItemController::class, 'storeLexeme'])->name('api.lessons.lexemes.store');
    Route::put('/lessons/{lesson}/lexemes/{item}', [LessonItemController::class, 'updateLexeme'])->name('api.lessons.lexemes.update');
    Route::delete('/lessons/{lesson}/lexemes/{item}', [LessonItemController::class, 'deleteLexeme'])->name('api.lessons.lexemes.destroy');
    Route::post('/lessons/{lesson}/lexemes/{item}/restore', [LessonItemController::class, 'restoreLexeme'])->name('api.lessons.lexemes.restore');

    Route::post('/lessons/{lesson}/grammar', [LessonItemController::class, 'storeGrammar'])->name('api.lessons.grammar.store');
    Route::post('/lessons/{lesson}/grammar/{item}/add-to-my-grammar', [LessonItemController::class, 'addGrammarToMyGrammar'])
        ->whereNumber('item')->name('api.lessons.grammar.add-to-my-grammar');
    Route::put('/lessons/{lesson}/grammar/{item}', [LessonItemController::class, 'updateGrammar'])->name('api.lessons.grammar.update');
    Route::delete('/lessons/{lesson}/grammar/{item}', [LessonItemController::class, 'deleteGrammar'])->name('api.lessons.grammar.destroy');
    Route::post('/lessons/{lesson}/grammar/{item}/restore', [LessonItemController::class, 'restoreGrammar'])->name('api.lessons.grammar.restore');

    Route::post('/lessons/{lesson}/corrections', [LessonItemController::class, 'storeCorrection'])->name('api.lessons.corrections.store');
    Route::put('/lessons/{lesson}/corrections/{item}', [LessonItemController::class, 'updateCorrection'])->name('api.lessons.corrections.update');
    Route::delete('/lessons/{lesson}/corrections/{item}', [LessonItemController::class, 'deleteCorrection'])->name('api.lessons.corrections.destroy');
    Route::post('/lessons/{lesson}/corrections/{item}/restore', [LessonItemController::class, 'restoreCorrection'])->name('api.lessons.corrections.restore');

});
