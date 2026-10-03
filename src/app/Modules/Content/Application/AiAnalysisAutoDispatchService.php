<?php

namespace App\Modules\Content\Application;

use App\Contracts\Ai\AiAnalysisRunConfig;
use App\Contracts\Ai\AiAnalysisRunDispatcher;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\User\Application\Contracts\AnalysisCreatorPreferencesReaderInterface;
use App\Support\AiConfig;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Auto-triggers AI candidate extraction once Content finishes mechanical
 * processing (status -> ready), instead of requiring the admin's manual
 * "Analyze with AI" button click. Admin-curated content always qualifies;
 * end-user submissions are capped by a per-submitter daily quota since this
 * dispatches a billed OpenAI call from a public-facing endpoint.
 */
class AiAnalysisAutoDispatchService
{
    public function __construct(
        private readonly AiAnalysisRunDispatcher $runs,
        private readonly AnalysisCreatorPreferencesReaderInterface $creatorPreferences,
    ) {}

    /** AI disabled, or this content has no transcript to analyze. */
    public const RESULT_UNAVAILABLE = 'unavailable';

    /** A run is already pending/running — dispatching again would duplicate it. */
    public const RESULT_ALREADY_RUNNING = 'already_running';

    /** User-submitted content only: the submitter's daily analysis quota is exhausted. */
    public const RESULT_QUOTA_EXCEEDED = 'quota_exceeded';

    public const RESULT_DISPATCHED = 'dispatched';

    /**
     * Called automatically from ProcessContentJob right after mechanical
     * processing, and manually from ContentAiSuggestionsController::reanalyze()
     * for a submitter who wants a fresh pass (e.g. after this class's own
     * prompt/thoroughness defaults changed, or their profile's target level
     * changed) — same guards either way, so a manual re-run can never bypass
     * the quota or pile up a second concurrent run.
     */
    public function dispatchFor(Content $content): string
    {
        if (! AiConfig::isEnabled() || ! $content->hasTranscript()) {
            return self::RESULT_UNAVAILABLE;
        }

        if ($this->runs->hasActiveRun($content->id)) {
            return self::RESULT_ALREADY_RUNNING;
        }

        if ($content->origin === 'user-submitted' && ! $this->consumeUserQuota($content)) {
            return self::RESULT_QUOTA_EXCEEDED;
        }

        $creator = $content->created_by ? $this->creatorPreferences->forUser($content->created_by) : null;

        $config = AiAnalysisRunConfig::fromArray([
            // Content's own curated level wins when set; the submitter's
            // self-reported level is only a soft fallback, not an override.
            'target_level' => $content->level ?? ($creator['current_level'] ?? null),
            'translation_language' => $creator['translation_language'] ?? null,
            // Defaults to extracting every notable word rather than a
            // curated subset — the learner decides what to skip/filter
            // afterward, the pipeline no longer pre-curates for them — but a
            // learner can dial this back via their own profile setting
            // (User::ai_extraction_thoroughness) if "thorough" is more than
            // they want.
            'thoroughness' => $creator['ai_extraction_thoroughness'] ?? AiAnalysisRunConfig::THOROUGHNESS_THOROUGH,
        ]);

        $this->runs->start($content->id, $config);

        return self::RESULT_DISPATCHED;
    }

    private function consumeUserQuota(Content $content): bool
    {
        $userId = $content->created_by;
        if (! $userId) {
            return false;
        }

        $limit = (int) config('ai.rate_limits.user_submission_analysis_per_day', 0);
        if ($limit <= 0) {
            return false;
        }

        $key = 'ai:rate_limit:content_analysis:'.$userId.':'.now()->format('Y-m-d');
        $count = (int) Cache::get($key, 0);

        if ($count >= $limit) {
            Log::info('Auto AI analysis skipped: daily quota reached', [
                'content_id' => $content->id,
                'user_id' => $userId,
            ]);

            return false;
        }

        Cache::put($key, $count + 1, now()->endOfDay()->addSecond());

        return true;
    }
}
