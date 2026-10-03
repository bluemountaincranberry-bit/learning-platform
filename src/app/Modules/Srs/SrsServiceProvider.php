<?php

namespace App\Modules\Srs;

use App\Modules\Content\Application\Contracts\ContentReviewScheduleReaderInterface;
use App\Modules\Content\Contracts\Events\LexemeLearningStarted;
use App\Modules\Content\Contracts\Events\LexemeLearningStopped;
use App\Modules\Srs\Application\Contracts\ExerciseReviewSchedulerInterface;
use App\Modules\Srs\Application\Contracts\ReviewMistakesReaderInterface;
use App\Modules\Srs\Application\Contracts\ReviewScheduleReaderInterface;
use App\Modules\Srs\Application\Contracts\SrsRepositoryInterface;
use App\Modules\Srs\Application\Contracts\SrsServiceInterface;
use App\Modules\Srs\Application\ExerciseReviewScheduler;
use App\Modules\Srs\Application\ReviewMistakesReader;
use App\Modules\Srs\Application\ReviewScheduleReader;
use App\Modules\Srs\Application\SrsService;
use App\Modules\Srs\Infrastructure\Persistence\EloquentSrsRepository;
use App\Modules\Srs\Interfaces\Listeners\CreateSrsCardOnLearningStartedListener;
use App\Modules\Srs\Interfaces\Listeners\DeleteSrsCardOnLearningStoppedListener;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class SrsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SrsServiceInterface::class, SrsService::class);
        $this->app->bind(ReviewScheduleReaderInterface::class, ReviewScheduleReader::class);
        $this->app->bind(ContentReviewScheduleReaderInterface::class, ReviewScheduleReader::class);
        $this->app->bind(ReviewMistakesReaderInterface::class, ReviewMistakesReader::class);
        $this->app->bind(ExerciseReviewSchedulerInterface::class, ExerciseReviewScheduler::class);
        $this->app->bind(SrsRepositoryInterface::class, EloquentSrsRepository::class);
    }

    public function boot(): void
    {
        Route::middleware('api')
            ->prefix('api')
            ->group(app_path('Modules/Srs/Routes/api.php'));

        Event::listen(LexemeLearningStarted::class, CreateSrsCardOnLearningStartedListener::class);
        Event::listen(LexemeLearningStopped::class, DeleteSrsCardOnLearningStoppedListener::class);
    }
}
