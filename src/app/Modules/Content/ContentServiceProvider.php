<?php

namespace App\Modules\Content;

use App\Contracts\VideoTitleFetcherInterface;
use App\Contracts\YoutubeTranscriptFetcherInterface;
use App\Modules\Content\Actions\RequestContentProcessing;
use App\Modules\Content\Application\AcceptedCandidateWriter;
use App\Modules\Content\Application\CandidateAnalysisStore;
use App\Modules\Content\Application\CandidateMatchStore;
use App\Modules\Content\Application\CandidateModeration;
use App\Modules\Content\Application\CatalogDuplicates\ForeignKeyGraph;
use App\Modules\Content\Application\CatalogDuplicates\GrammarRuleMerger;
use App\Modules\Content\Application\ContentAnalysisSourceReader;
use App\Modules\Content\Application\ContentLexemeReferenceReader;
use App\Modules\Content\Application\ContentResetOperations;
use App\Modules\Content\Application\ContentService;
use App\Modules\Content\Application\ContentTitleReader;
use App\Modules\Content\Application\ContentTokenizer;
use App\Modules\Content\Application\ContentViewAuthorization;
use App\Modules\Content\Application\Contracts\AcceptedCandidateWriterInterface;
use App\Modules\Content\Application\Contracts\CandidateAnalysisStoreInterface;
use App\Modules\Content\Application\Contracts\CandidateMatchStoreInterface;
use App\Modules\Content\Application\Contracts\CandidateModerationInterface;
use App\Modules\Content\Application\Contracts\ContentAnalysisSourceReaderInterface;
use App\Modules\Content\Application\Contracts\ContentLexemeReferenceReaderInterface;
use App\Modules\Content\Application\Contracts\ContentProcessingOrchestratorInterface;
use App\Modules\Content\Application\Contracts\ContentRepositoryInterface;
use App\Modules\Content\Application\Contracts\ContentResetOperationsInterface;
use App\Modules\Content\Application\Contracts\ContentServiceInterface;
use App\Modules\Content\Application\Contracts\ContentTitleReaderInterface;
use App\Modules\Content\Application\Contracts\ContentTokenizerInterface;
use App\Modules\Content\Application\Contracts\ContentViewAuthorizationInterface;
use App\Modules\Content\Application\Contracts\DraftContentCreatorInterface;
use App\Modules\Content\Application\Contracts\EmbeddingSourceReaderInterface;
use App\Modules\Content\Application\Contracts\ExerciseContentGatewayInterface;
use App\Modules\Content\Application\Contracts\GrammarCatalogServiceInterface;
use App\Modules\Content\Application\Contracts\GrammarExerciseDraftsInterface;
use App\Modules\Content\Application\Contracts\GrammarExplanationReaderInterface;
use App\Modules\Content\Application\Contracts\GrammarProgressServiceInterface;
use App\Modules\Content\Application\Contracts\GrammarRuleExampleGenerationsInterface;
use App\Modules\Content\Application\Contracts\GrammarRuleExampleReaderInterface;
use App\Modules\Content\Application\Contracts\GrammarRuleExampleWriterInterface;
use App\Modules\Content\Application\Contracts\GrammarRuleMergeParticipant;
use App\Modules\Content\Application\Contracts\GrammarRuleTitleReaderInterface;
use App\Modules\Content\Application\Contracts\GraphTestContentFactoryInterface;
use App\Modules\Content\Application\Contracts\LearnedLexemeCatalogInterface;
use App\Modules\Content\Application\Contracts\LexemeCatalogSearchInterface;
use App\Modules\Content\Application\Contracts\LexemeEnrichmentCatalogInterface;
use App\Modules\Content\Application\Contracts\LexemePresentationReaderInterface;
use App\Modules\Content\Application\Contracts\LexemeServiceInterface;
use App\Modules\Content\Application\Contracts\ManualLexemeCandidateStoreInterface;
use App\Modules\Content\Application\Contracts\MyWordsCatalogInterface;
use App\Modules\Content\Application\Contracts\PdfTextExtractorInterface;
use App\Modules\Content\Application\Contracts\RagSourceReaderInterface;
use App\Modules\Content\Application\Contracts\RecommendationCatalogInterface;
use App\Modules\Content\Application\Contracts\SelfCheckLexemeCatalogInterface;
use App\Modules\Content\Application\Contracts\SentencePracticeCatalogInterface;
use App\Modules\Content\Application\Contracts\SrsReviewReferenceReaderInterface;
use App\Modules\Content\Application\Contracts\SubtitleTextExtractorInterface;
use App\Modules\Content\Application\Contracts\TrainingLexemeCatalogInterface;
use App\Modules\Content\Application\Contracts\TranscriptLexemeLinkerInterface;
use App\Modules\Content\Application\DraftContentCreator;
use App\Modules\Content\Application\EmbeddingSourceReader;
use App\Modules\Content\Application\ExerciseContentGateway;
use App\Modules\Content\Application\GrammarCatalogService;
use App\Modules\Content\Application\GrammarExerciseDrafts;
use App\Modules\Content\Application\GrammarExplanationReader;
use App\Modules\Content\Application\GrammarProgressService;
use App\Modules\Content\Application\GrammarRuleExampleGenerations;
use App\Modules\Content\Application\GrammarRuleExampleReader;
use App\Modules\Content\Application\GrammarRuleExampleWriter;
use App\Modules\Content\Application\GrammarRuleTitleReader;
use App\Modules\Content\Application\GraphTestContentFactory;
use App\Modules\Content\Application\LearnedLexemeCatalog;
use App\Modules\Content\Application\LexemeCatalogSearch;
use App\Modules\Content\Application\LexemeEnrichmentCatalog;
use App\Modules\Content\Application\LexemePresentationReader;
use App\Modules\Content\Application\LexemeService;
use App\Modules\Content\Application\ManualLexemeCandidateStore;
use App\Modules\Content\Application\MyWordsCatalog;
use App\Modules\Content\Application\RagSourceReader;
use App\Modules\Content\Application\RecommendationCatalog;
use App\Modules\Content\Application\SelfCheckLexemeCatalog;
use App\Modules\Content\Application\SentencePracticeCatalog;
use App\Modules\Content\Application\SrsReviewReferenceReader;
use App\Modules\Content\Application\TrainingLexemeCatalog;
use App\Modules\Content\Application\Transcript\TranscriptSegmentStore;
use App\Modules\Content\Domain\Events\ContentProcessingRequested;
use App\Modules\Content\Domain\Events\ContentSubmitted;
use App\Modules\Content\Infrastructure\Integrations\FallbackYoutubeTranscriptFetcher;
use App\Modules\Content\Infrastructure\Integrations\LibraryYoutubeTranscriptFetcher;
use App\Modules\Content\Infrastructure\Integrations\PlaywrightYoutubeTranscriptFetcher;
use App\Modules\Content\Infrastructure\Integrations\StubYoutubeTranscriptFetcher;
use App\Modules\Content\Infrastructure\Integrations\SupadataYoutubeTranscriptFetcher;
use App\Modules\Content\Infrastructure\Integrations\YoutubeOEmbedTitleFetcher;
use App\Modules\Content\Infrastructure\Integrations\YoutubeWebTranscriptFetcher;
use App\Modules\Content\Infrastructure\Pdf\PopplerPdfTextExtractor;
use App\Modules\Content\Infrastructure\Persistence\ContentRepository;
use App\Modules\Content\Infrastructure\Subtitles\SrtVttSubtitleTextExtractor;
use App\Modules\Content\Interfaces\Listeners\DispatchContentProcessingListener;
use App\Modules\Content\Interfaces\Listeners\PublishContentSubmittedToKafka;
use App\Modules\Content\Interfaces\Listeners\RequestContentProcessingOnSubmissionListener;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ContentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(YoutubeTranscriptFetcherInterface::class, function () {
            $driver = (string) config('transcripts.youtube.driver', 'stub');
            $driverConfig = config("transcripts.youtube.drivers.{$driver}", []);

            $createFetcher = function (string $provider): YoutubeTranscriptFetcherInterface {
                $config = config("transcripts.youtube.drivers.{$provider}", []);

                return match ($provider) {
                    'youtube_web' => new YoutubeWebTranscriptFetcher(
                        baseUrl: (string) ($config['base_url'] ?? 'https://www.youtube.com'),
                        timeout: (int) ($config['timeout'] ?? 15),
                        userAgent: (string) ($config['user_agent'] ?? 'Mozilla/5.0'),
                    ),
                    'youtube_playwright' => new PlaywrightYoutubeTranscriptFetcher(
                        baseUrl: (string) ($config['base_url'] ?? 'http://browser:3000'),
                        timeout: (int) ($config['timeout'] ?? 60),
                    ),
                    'youtube_transcript_node', 'youtube_transcript_python' => new LibraryYoutubeTranscriptFetcher(
                        baseUrl: (string) ($config['base_url'] ?? ''),
                        endpoint: (string) ($config['endpoint'] ?? '/transcript'),
                        timeout: (int) ($config['timeout'] ?? 45),
                    ),
                    'supadata' => new SupadataYoutubeTranscriptFetcher(
                        baseUrl: (string) ($config['base_url'] ?? 'https://api.supadata.ai/v1'),
                        apiKey: (string) ($config['api_key'] ?? ''),
                        mode: (string) ($config['mode'] ?? 'native'),
                        timeout: (int) ($config['timeout'] ?? 30),
                        pollIntervalMs: (int) ($config['poll_interval_ms'] ?? 1000),
                        maxPolls: (int) ($config['max_polls'] ?? 10),
                        preferredLanguage: config('transcripts.youtube.preferred_language'),
                    ),
                    default => new StubYoutubeTranscriptFetcher,
                };
            };

            if ($driver === 'fallback') {
                return new FallbackYoutubeTranscriptFetcher(array_map(
                    $createFetcher,
                    config('transcripts.youtube.drivers.fallback.providers', ['youtube_web', 'supadata']),
                ));
            }

            return $createFetcher($driver);
        });

        $this->app->bind(VideoTitleFetcherInterface::class, YoutubeOEmbedTitleFetcher::class);
        $this->app->bind(ContentProcessingOrchestratorInterface::class, RequestContentProcessing::class);
        $this->app->bind(CandidateModerationInterface::class, CandidateModeration::class);
        $this->app->bind(CandidateAnalysisStoreInterface::class, CandidateAnalysisStore::class);
        $this->app->bind(AcceptedCandidateWriterInterface::class, AcceptedCandidateWriter::class);
        $this->app->bind(CandidateMatchStoreInterface::class, CandidateMatchStore::class);
        $this->app->bind(ManualLexemeCandidateStoreInterface::class, ManualLexemeCandidateStore::class);
        $this->app->bind(MyWordsCatalogInterface::class, MyWordsCatalog::class);
        $this->app->bind(ContentServiceInterface::class, ContentService::class);
        $this->app->bind(ContentViewAuthorizationInterface::class, ContentViewAuthorization::class);
        $this->app->bind(ContentAnalysisSourceReaderInterface::class, ContentAnalysisSourceReader::class);
        $this->app->bind(DraftContentCreatorInterface::class, DraftContentCreator::class);
        $this->app->bind(ContentLexemeReferenceReaderInterface::class, ContentLexemeReferenceReader::class);
        $this->app->bind(ContentResetOperationsInterface::class, ContentResetOperations::class);
        $this->app->bind(EmbeddingSourceReaderInterface::class, EmbeddingSourceReader::class);
        $this->app->bind(ExerciseContentGatewayInterface::class, ExerciseContentGateway::class);
        $this->app->bind(LexemeCatalogSearchInterface::class, LexemeCatalogSearch::class);
        $this->app->bind(LexemeEnrichmentCatalogInterface::class, LexemeEnrichmentCatalog::class);
        $this->app->bind(LearnedLexemeCatalogInterface::class, LearnedLexemeCatalog::class);
        $this->app->bind(LexemePresentationReaderInterface::class, LexemePresentationReader::class);
        $this->app->bind(RagSourceReaderInterface::class, RagSourceReader::class);
        $this->app->bind(RecommendationCatalogInterface::class, RecommendationCatalog::class);
        $this->app->bind(SrsReviewReferenceReaderInterface::class, SrsReviewReferenceReader::class);
        $this->app->bind(SelfCheckLexemeCatalogInterface::class, SelfCheckLexemeCatalog::class);
        $this->app->bind(SentencePracticeCatalogInterface::class, SentencePracticeCatalog::class);
        $this->app->bind(TranscriptLexemeLinkerInterface::class, TranscriptSegmentStore::class);
        $this->app->bind(TrainingLexemeCatalogInterface::class, TrainingLexemeCatalog::class);
        $this->app->bind(ContentRepositoryInterface::class, ContentRepository::class);
        $this->app->bind(ContentTokenizerInterface::class, ContentTokenizer::class);
        $this->app->bind(ContentTitleReaderInterface::class, ContentTitleReader::class);
        $this->app->bind(GrammarCatalogServiceInterface::class, GrammarCatalogService::class);
        $this->app->bind(GrammarExerciseDraftsInterface::class, GrammarExerciseDrafts::class);
        $this->app->bind(GrammarExplanationReaderInterface::class, GrammarExplanationReader::class);
        $this->app->bind(GrammarProgressServiceInterface::class, GrammarProgressService::class);
        $this->app->bind(GrammarRuleExampleGenerationsInterface::class, GrammarRuleExampleGenerations::class);
        $this->app->bind(GrammarRuleExampleReaderInterface::class, GrammarRuleExampleReader::class);
        $this->app->bind(GrammarRuleExampleWriterInterface::class, GrammarRuleExampleWriter::class);
        $this->app->bind(GrammarRuleTitleReaderInterface::class, GrammarRuleTitleReader::class);
        $this->app->bind(GraphTestContentFactoryInterface::class, GraphTestContentFactory::class);
        $this->app->bind(LexemeServiceInterface::class, LexemeService::class);
        $this->app->bind(PdfTextExtractorInterface::class, PopplerPdfTextExtractor::class);
        $this->app->bind(SubtitleTextExtractorInterface::class, SrtVttSubtitleTextExtractor::class);
        $this->app->bind(GrammarRuleMerger::class, fn ($app): GrammarRuleMerger => new GrammarRuleMerger(
            $app->make(ForeignKeyGraph::class),
            $app->tagged(GrammarRuleMergeParticipant::TAG),
        ));
    }

    public function boot(): void
    {
        Route::middleware('api')
            ->prefix('api')
            ->group(app_path('Modules/Content/Routes/api.php'));

        Event::listen(ContentSubmitted::class, RequestContentProcessingOnSubmissionListener::class);
        Event::listen(ContentProcessingRequested::class, DispatchContentProcessingListener::class);

        if (config('kafka.enabled')) {
            Event::listen(ContentSubmitted::class, PublishContentSubmittedToKafka::class);
        }
    }
}
