<?php

use App\Modules\Learning\Interfaces\Http\Controllers\SelfCheckController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function (): void {
    Route::get('/self-check/start', [SelfCheckController::class, 'start'])->name('api.self-check.start');
    Route::post('/self-check/submit', [SelfCheckController::class, 'submit'])->name('api.self-check.submit');
});
