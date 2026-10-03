<?php

namespace App\Modules\Learning;

use App\Modules\Content\Application\Contracts\GrammarProgressStoreInterface;
use App\Modules\Learning\Application\Contracts\PronunciationAssessmentProviderInterface;
use App\Modules\Learning\Application\Contracts\SpeechToTextProviderInterface;
use App\Modules\Learning\Application\GrammarProgressStore;
use App\Modules\Learning\Application\LearningStatsService;
use App\Modules\Learning\Application\ReviewOutcomeHandler;
use App\Modules\Learning\Domain\Events\ExerciseCompleted;
use App\Modules\Learning\Infrastructure\AzurePronunciationAssessmentProvider;
use App\Modules\Learning\Infrastructure\DemoPronunciationAssessmentProvider;
use App\Modules\Learning\Infrastructure\OpenAiSpeechToTextProvider;
use App\Modules\Learning\Infrastructure\StubPronunciationAssessmentProvider;
use App\Modules\Learning\Infrastructure\StubSpeechToTextProvider;
use App\Modules\Learning\Interfaces\Listeners\PublishExerciseCompletedToKafka;
use App\Modules\Srs\Application\Contracts\ReviewOutcomeHandlerInterface;
use App\Modules\User\Application\Contracts\LearningStatsReaderInterface;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class LearningServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(GrammarProgressStoreInterface::class, GrammarProgressStore::class);
        $this->app->bind(LearningStatsReaderInterface::class, LearningStatsService::class);
        $this->app->bind(ReviewOutcomeHandlerInterface::class, ReviewOutcomeHandler::class);
        $this->app->bind(SpeechToTextProviderInterface::class, function (): SpeechToTextProviderInterface {
            $key = (string) config('ai.openai.api_key', '');

            return $key === '' ? new StubSpeechToTextProvider : new OpenAiSpeechToTextProvider($key, (int) config('ai.timeout', 60));
        });
        $this->app->bind(PronunciationAssessmentProviderInterface::class, function (): PronunciationAssessmentProviderInterface {
            if (config('ai.azure_speech.enabled') && config('ai.azure_speech.key') && config('ai.azure_speech.region')) {
                return new AzurePronunciationAssessmentProvider(
                    (string) config('ai.azure_speech.key'),
                    (string) config('ai.azure_speech.region'),
                    (int) config('ai.timeout', 60),
                );
            }
            if (app()->environment(['local', 'testing'])) {
                return new DemoPronunciationAssessmentProvider;
            }

            return new StubPronunciationAssessmentProvider;
        });
    }

    public function boot(): void
    {
        Route::middleware('api')
            ->prefix('api')
            ->group(app_path('Modules/Learning/Routes/progress.php'));

        Route::middleware('api')
            ->prefix('api')
            ->group(app_path('Modules/Learning/Routes/self-check.php'));

        Route::middleware('api')
            ->prefix('api')
            ->group(app_path('Modules/Learning/Routes/training.php'));

        // Task 4.13 — same conditional registration as ContentServiceProvider's
        // PublishContentSubmittedToKafka: only listen when Kafka is enabled.
        if (config('kafka.enabled')) {
            Event::listen(ExerciseCompleted::class, PublishExerciseCompletedToKafka::class);
        }
    }
}
