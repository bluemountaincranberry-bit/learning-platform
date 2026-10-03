<?php

use App\Modules\Learning\Interfaces\Http\Controllers\TrainingSessionController;
use App\Modules\Learning\Interfaces\Http\Controllers\ExerciseAttemptController;
use App\Modules\Learning\Interfaces\Http\Controllers\LearningFlowController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function (): void {
    Route::get('/training/review-queue', [TrainingSessionController::class, 'reviewQueue'])->name('api.training.review-queue');
    Route::get('/training/selected-lexemes', [TrainingSessionController::class, 'selectedLexemes'])->name('api.training.selected-lexemes');
    Route::get('/learning/flow', [LearningFlowController::class, 'show'])->name('api.learning.flow.show');
    Route::put('/learning/flow/preferences', [LearningFlowController::class, 'update'])->name('api.learning.flow.preferences');
    Route::post('/learning/exercise-attempts', [ExerciseAttemptController::class, 'store'])->name('api.learning.exercise-attempts.store');
    Route::get('/learning/exercise-attempts/{exerciseAttempt}', [ExerciseAttemptController::class, 'show'])->name('api.learning.exercise-attempts.show');
});
