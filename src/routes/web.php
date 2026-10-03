<?php

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class)->name('health');

// Old bookmarks pointing at the previous /app/* prefix still resolve.
Route::get('/app/{any?}', function (string $any = '') {
    return redirect('/'.$any);
})->where('any', '.*');

// The SPA owns every path — it handles its own auth via the API guard.
Route::get('/{any?}', function () {
    return view('spa');
})->where('any', '.*')->name('spa');
