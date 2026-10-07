<?php

namespace App\Providers;

use App\Contracts\KafkaProducerInterface;
use App\Modules\Ai\Application\AiCandidateApplyService;
use App\Modules\Content\Application\Contracts\CandidateApplicationInterface;
use App\Modules\Content\Application\Contracts\ContentLearnerStateReaderInterface;
use App\Modules\Content\Application\Contracts\ContentReadinessProgressInterface;
use App\Modules\Content\Application\Contracts\GrammarConfidenceRecorderInterface;
use App\Modules\Content\Application\Contracts\LearningProgressReferencesInterface;
use App\Modules\Content\Application\Contracts\LexemeLearningStateWriterInterface;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\Content as ContentAggregate;
use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Domain\Models\Lexeme;
use App\Modules\Learning\Application\ContentLearnerStateReader;
use App\Modules\Learning\Application\ContentReadinessProgress;
use App\Modules\Learning\Application\Contracts\SentencePracticeLearnerContextInterface;
use App\Modules\Learning\Application\Contracts\StudentVocabularyReaderInterface;
use App\Modules\Learning\Application\GrammarConfidenceRecorder;
use App\Modules\Learning\Application\LearningProgressReferences;
use App\Modules\Learning\Application\LexemeLearningStateWriter;
use App\Modules\Learning\Application\SentencePracticeLearnerContext;
use App\Modules\Learning\Application\StudentVocabularyReader;
use App\Modules\Learning\Domain\Models\Lesson;
use App\Modules\Srs\Application\Contracts\ReviewGradePolicyInterface;
use App\Modules\Srs\Application\ReviewGradePolicy;
use App\Modules\Srs\Domain\Models\SrsCard;
use App\Modules\User\Models\User;
use App\Policies\ContentPolicy;
use App\Policies\GrammarRulePolicy;
use App\Policies\LessonPolicy;
use App\Policies\LexemePolicy;
use App\Policies\SrsCardPolicy;
use App\Support\NullKafkaProducer;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Kafka producer: when disabled or no real client yet, use null producer (KAFKA-02 will add real publish)
        $this->app->bind(KafkaProducerInterface::class, NullKafkaProducer::class);
        $this->app->bind(CandidateApplicationInterface::class, AiCandidateApplyService::class);
        $this->app->bind(ReviewGradePolicyInterface::class, ReviewGradePolicy::class);
        $this->app->bind(StudentVocabularyReaderInterface::class, StudentVocabularyReader::class);
        $this->app->bind(LearningProgressReferencesInterface::class, LearningProgressReferences::class);
        $this->app->bind(ContentReadinessProgressInterface::class, ContentReadinessProgress::class);
        $this->app->bind(SentencePracticeLearnerContextInterface::class, SentencePracticeLearnerContext::class);
        $this->app->bind(GrammarConfidenceRecorderInterface::class, GrammarConfidenceRecorder::class);
        $this->app->bind(ContentLearnerStateReaderInterface::class, ContentLearnerStateReader::class);
        $this->app->bind(LexemeLearningStateWriterInterface::class, LexemeLearningStateWriter::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::morphMap([
            'App\\Models\\User' => User::class,
        ]);

        Gate::define('access-admin-panel', fn (User $user): bool => $user->hasAnyRole(['admin', 'editor', 'moderator']));
        Gate::define('manage-content', fn (User $user): bool => $user->hasAnyRole(['admin', 'editor']));
        Gate::define('moderate-content', fn (User $user): bool => $user->hasAnyRole(['admin', 'moderator']));
        Gate::define('manage-users', fn (User $user): bool => $user->hasRole('admin'));
        // Prompt/graph builder (graph-builder groundwork): editing prompt
        // text or a graph's node wiring changes what every learner-facing
        // AI call actually does — kept admin-only, not editor/moderator,
        // unlike manage-content above.
        Gate::define('manage-ai-builder', fn (User $user): bool => $user->hasRole('admin'));
        Gate::define('manage-learning-flows', fn (User $user): bool => $user->hasRole('admin'));
        Gate::define('viewPulse', fn (?User $user): bool => $user !== null && $user->hasRole('admin'));

        // Who can talk to TutorAgent (EPIC-3.5/3.8): staff accounts (admin/
        // editor/moderator) use their own admin-only ContentAgentService via
        // the Filament panel (access-admin-panel above) — the student-facing
        // TutorAgent surface is for learner accounts, mirroring ADR-001's
        // split ("admin works with content drafts, student works with their
        // own personal learning data") at the gate level, the same pattern
        // access-admin-panel already uses to keep the reverse boundary.
        Gate::define('access-tutor-agent', fn (User $user): bool => ! $user->hasAnyRole(['admin', 'editor', 'moderator']));

        Gate::policy(Content::class, ContentPolicy::class);
        Gate::policy(ContentAggregate::class, ContentPolicy::class);
        Gate::policy(GrammarRule::class, GrammarRulePolicy::class);
        Gate::policy(SrsCard::class, SrsCardPolicy::class);
        Gate::policy(Lesson::class, LessonPolicy::class);
        Gate::policy(Lexeme::class, LexemePolicy::class);

        // This is an API-only app with no `password.reset` web view — point
        // the notification at the SPA's own reset-password route instead of
        // Laravel's default named-route lookup, which would fail here.
        ResetPassword::createUrlUsing(fn (mixed $notifiable, string $token): string => sprintf(
            '%s/reset-password?token=%s&email=%s',
            rtrim((string) config('app.url'), '/'),
            $token,
            urlencode($notifiable->getEmailForPasswordReset()),
        ));
    }
}
