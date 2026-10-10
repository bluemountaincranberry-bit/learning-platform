<?php

namespace App\Modules\Interview;

use App\Contracts\Ai\InterviewDraftWriter;
use App\Modules\Interview\Application\Contracts\InterviewSessionContextReader;
use App\Modules\Interview\Application\InterviewDraftService;
use App\Modules\Interview\Application\InterviewSessionContext;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class InterviewServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(InterviewSessionContextReader::class, InterviewSessionContext::class);
        $this->app->bind(InterviewDraftWriter::class, InterviewDraftService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../../database/migrations');
        Route::middleware('api')->prefix('api')->group(__DIR__.'/Routes/api.php');
    }
}
