<?php

namespace App\Modules\Learning;

use App\Contracts\Ai\LessonAnalysisStoreInterface;
use App\Contracts\Ai\LessonNotesWriterInterface;
use App\Contracts\Ai\SpeakingMistakePracticeReaderInterface;
use App\Contracts\Ai\SpeakingMistakeRecorderInterface;
use App\Modules\Content\Application\Contracts\GrammarProgressStoreInterface;
use App\Modules\Content\Application\Contracts\GrammarRuleMergeParticipant;
use App\Modules\Content\Application\Contracts\PersonalLexemeReconcilerInterface;
use App\Modules\Content\Contracts\Events\LexemeLearningStarted;
use App\Modules\Learning\Application\Contracts\PersonalVocabularyWriterInterface;
use App\Modules\Learning\Application\Contracts\PronunciationAssessmentProviderInterface;
use App\Modules\Learning\Application\Contracts\SpeechToTextProviderInterface;
use App\Modules\Learning\Application\GrammarProgressMergeParticipant;
use App\Modules\Learning\Application\GrammarProgressStore;
use App\Modules\Learning\Application\LearningStatsService;
use App\Modules\Learning\Application\LessonStore;
use App\Modules\Learning\Application\PersonalLexemeReconciler;
use App\Modules\Learning\Application\PersonalVocabularyWriter;
use App\Modules\Learning\Application\ReviewOutcomeHandler;
use App\Modules\Learning\Application\SpeakingMistakePracticeReader;
use App\Modules\Learning\Application\SpeakingMistakeRecorder;
use App\Modules\Learning\Application\SpeechToTextProviderFactory;
use App\Modules\Learning\Domain\Events\ExerciseCompleted;
use App\Modules\Learning\Domain\Events\GrammarPracticeCompleted;
use App\Modules\Learning\Infrastructure\AzurePronunciationAssessmentProvider;
use App\Modules\Learning\Infrastructure\DemoPronunciationAssessmentProvider;
use App\Modules\Learning\Infrastructure\StubPronunciationAssessmentProvider;
use App\Modules\Learning\Infrastructure\StubSpeechToTextProvider;
use App\Modules\Learning\Interfaces\Listeners\AddPracticedRuleToMyGrammar;
use App\Modules\Learning\Interfaces\Listeners\PublishExerciseCompletedToKafka;
use App\Modules\Learning\Interfaces\Listeners\RecordUserLexemeSourceOnLearningStarted;
use App\Modules\Learning\Interfaces\Listeners\TopUpGrammarExercisePool;
use App\Modules\Srs\Application\Contracts\ReviewOutcomeHandlerInterface;
use App\Modules\User\Application\Contracts\LearningStatsReaderInterface;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class LearningServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LessonAnalysisStoreInterface::class, LessonStore::class);
        $this->app->bind(LessonNotesWriterInterface::class, LessonStore::class);
        $this->app->bind(SpeakingMistakeRecorderInterface::class, SpeakingMistakeRecorder::class);
        $this->app->bind(SpeakingMistakePracticeReaderInterface::class, SpeakingMistakePracticeReader::class);
        $this->app->bind(GrammarProgressStoreInterface::class, GrammarProgressStore::class);
        $this->app->tag([GrammarProgressMergeParticipant::class], GrammarRuleMergeParticipant::TAG);
        $this->app->bind(LearningStatsReaderInterface::class, LearningStatsService::class);
        $this->app->bind(ReviewOutcomeHandlerInterface::class, ReviewOutcomeHandler::class);
        $this->app->bind(PersonalLexemeReconcilerInterface::class, PersonalLexemeReconciler::class);
        $this->app->bind(PersonalVocabularyWriterInterface::class, PersonalVocabularyWriter::class);
        $this->app->bind(SpeechToTextProviderInterface::class, function (): SpeechToTextProviderInterface {
            $provider = (string) config('ai.speech_to_text_provider', 'local_whisper');
            if ($provider === 'openai' && (string) config('ai.openai.api_key', '') === '') {
                return new StubSpeechToTextProvider;
            }

            return app(SpeechToTextProviderFactory::class)->make($provider);
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
        Event::listen(LexemeLearningStarted::class, RecordUserLexemeSourceOnLearningStarted::class);

        Route::middleware('api')->prefix('api')->group(app_path('Modules/Learning/Routes/lessons.php'));

        Route::middleware('api')
            ->prefix('api')
            ->group(app_path('Modules/Learning/Routes/progress.php'));

        Route::middleware('api')
            ->prefix('api')
            ->group(app_path('Modules/Learning/Routes/self-check.php'));

        Route::middleware('api')
            ->prefix('api')
            ->group(app_path('Modules/Learning/Routes/training.php'));

        Route::middleware('api')
            ->prefix('api')
            ->group(app_path('Modules/Learning/Routes/grammar-practice.php'));

        Event::listen(GrammarPracticeCompleted::class, AddPracticedRuleToMyGrammar::class);
        Event::listen(GrammarPracticeCompleted::class, TopUpGrammarExercisePool::class);

        // Task 4.13 — same conditional registration as ContentServiceProvider's
        // PublishContentSubmittedToKafka: only listen when Kafka is enabled.
        if (config('kafka.enabled')) {
            Event::listen(ExerciseCompleted::class, PublishExerciseCompletedToKafka::class);
        }
    }
}
