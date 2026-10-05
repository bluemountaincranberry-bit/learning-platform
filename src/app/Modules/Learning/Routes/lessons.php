<?php

use App\Modules\Learning\Interfaces\Http\Controllers\LessonController;
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

});
