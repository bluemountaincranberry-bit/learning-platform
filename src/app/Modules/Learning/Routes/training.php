<?php

use App\Modules\Learning\Interfaces\Http\Controllers\TrainingSessionController;
use App\Modules\Learning\Interfaces\Http\Controllers\ExerciseAttemptController;
use App\Modules\Learning\Interfaces\Http\Controllers\LearningFlowController;
use App\Modules\Learning\Interfaces\Http\Controllers\SpeechTranscriptionController;
use App\Modules\Learning\Interfaces\Http\Controllers\SpeakingMistakeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function (): void {
    Route::get('/training/review-queue', [TrainingSessionController::class, 'reviewQueue'])->name('api.training.review-queue');
    Route::get('/training/selected-lexemes', [TrainingSessionController::class, 'selectedLexemes'])->name('api.training.selected-lexemes');
    Route::get('/training/selected-canonical-lexemes', [TrainingSessionController::class, 'selectedCanonicalLexemes'])->name('api.training.selected-canonical-lexemes');
    Route::get('/learning/flow', [LearningFlowController::class, 'show'])->name('api.learning.flow.show');
    Route::put('/learning/flow/preferences', [LearningFlowController::class, 'update'])->name('api.learning.flow.preferences');
    Route::post('/learning/exercise-attempts', [ExerciseAttemptController::class, 'store'])->name('api.learning.exercise-attempts.store');
    Route::get('/learning/exercise-attempts/{exerciseAttempt}', [ExerciseAttemptController::class, 'show'])->name('api.learning.exercise-attempts.show');
    Route::get('/learning/speech/providers', [SpeechTranscriptionController::class, 'providers'])->name('api.learning.speech.providers');
    Route::post('/learning/speech/transcribe', [SpeechTranscriptionController::class, 'transcribe'])->middleware('throttle:15,1')->name('api.learning.speech.transcribe');
    Route::get('/learning/speaking-mistakes', [SpeakingMistakeController::class, 'index'])->name('api.learning.speaking-mistakes.index');
    Route::post('/learning/speaking-mistakes', [SpeakingMistakeController::class, 'store'])->name('api.learning.speaking-mistakes.store');
    Route::patch('/learning/speaking-mistakes/{mistake}', [SpeakingMistakeController::class, 'update'])->name('api.learning.speaking-mistakes.update');
    Route::post('/learning/speaking-mistakes/{mistake}/outcome', [SpeakingMistakeController::class, 'outcome'])->name('api.learning.speaking-mistakes.outcome');
});
