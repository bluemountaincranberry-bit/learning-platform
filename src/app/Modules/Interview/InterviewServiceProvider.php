<?php

namespace App\Modules\Interview;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class InterviewServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../../database/migrations');
        Route::middleware('api')->prefix('api')->group(__DIR__.'/Routes/api.php');
    }
}
