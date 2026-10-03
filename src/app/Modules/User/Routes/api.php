<?php

use App\Modules\User\Interfaces\Http\Controllers\AuthController;
use App\Modules\User\Interfaces\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', [AuthController::class, 'register'])
    ->middleware('throttle:10,1')
    ->name('api.auth.register');
Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:10,1')
    ->name('api.auth.login');
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])
    ->middleware('throttle:5,1')
    ->name('api.auth.forgot-password');
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])
    ->middleware('throttle:5,1')
    ->name('api.auth.reset-password');
Route::post('/auth/logout', [AuthController::class, 'logout'])
    ->middleware('auth:sanctum')
    ->name('api.auth.logout');
Route::get('/auth/me', [AuthController::class, 'me'])
    ->middleware('auth:sanctum')
    ->name('api.auth.me');

Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function (): void {
    Route::get('/profile', [ProfileController::class, 'show'])->name('api.profile.show');
    Route::put('/profile', [ProfileController::class, 'update'])->name('api.profile.update');
});
