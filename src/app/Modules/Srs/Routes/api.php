<?php

use App\Modules\Srs\Interfaces\Http\Controllers\SrsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function (): void {
    Route::get('/srs/due', [SrsController::class, 'due'])->name('api.srs.due');
    Route::post('/srs/review', [SrsController::class, 'review'])->name('api.srs.review');
});
