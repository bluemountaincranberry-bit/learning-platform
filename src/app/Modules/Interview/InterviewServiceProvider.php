<?php

namespace App\Modules\Interview;

use App\Modules\Interview\Application\Contracts\InterviewSessionContextReader;
use App\Modules\Interview\Application\InterviewSessionContext;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class InterviewServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(InterviewSessionContextReader::class, InterviewSessionContext::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../../database/migrations');
        Route::middleware('api')->prefix('api')->group(__DIR__.'/Routes/api.php');
    }
}
