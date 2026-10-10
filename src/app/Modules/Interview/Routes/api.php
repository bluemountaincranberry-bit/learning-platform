<?php

use App\Modules\Interview\Interfaces\Http\Controllers\InterviewController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:60,1'])->prefix('interview')->group(function (): void {
    Route::get('/topics', [InterviewController::class, 'topics']);
    Route::get('/tags', [InterviewController::class, 'tags']);
    Route::post('/topics', [InterviewController::class, 'storeTopic']);
    Route::put('/topics/{topic}', [InterviewController::class, 'updateTopic']);
    Route::delete('/topics/{topic}', [InterviewController::class, 'deleteTopic']);
    Route::get('/questions', [InterviewController::class, 'questions']);
    Route::post('/questions', [InterviewController::class, 'storeQuestion']);
    Route::get('/questions/{question}', [InterviewController::class, 'showQuestion']);
    Route::put('/questions/{question}', [InterviewController::class, 'updateQuestion']);
    Route::delete('/questions/{question}', [InterviewController::class, 'deleteQuestion']);
    Route::post('/questions/{question}/answers/{answer}/revisions/{revision}/restore', [InterviewController::class, 'restoreAnswerRevision']);
    Route::get('/drafts', [InterviewController::class, 'drafts']);
    Route::post('/drafts', [InterviewController::class, 'storeDraft']);
    Route::post('/drafts/{draft}/confirm', [InterviewController::class, 'confirmDraft']);
    Route::post('/drafts/{draft}/reject', [InterviewController::class, 'rejectDraft']);
    Route::get('/profile', [InterviewController::class, 'profile']);
    Route::put('/profile', [InterviewController::class, 'saveProfile']);
    Route::get('/sessions', [InterviewController::class, 'sessions']);
    Route::post('/sessions', [InterviewController::class, 'storeSession']);
    Route::get('/sessions/{session}', [InterviewController::class, 'showSession']);
    Route::post('/sessions/{session}/complete', [InterviewController::class, 'completeSession']);
    Route::post('/sessions/{session}/messages', [InterviewController::class, 'sendSessionMessage']);
});
