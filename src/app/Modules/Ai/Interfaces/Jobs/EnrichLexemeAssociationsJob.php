<?php

namespace App\Modules\Ai\Interfaces\Jobs;

use App\Exceptions\AiClientException;
use App\Modules\Ai\Application\LexemeEnrichmentService;
use App\Contracts\Ai\AiErrorMessage;
use App\Support\AiConfig;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EnrichLexemeAssociationsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function __construct(public int $lexemeId) {}

    public function tags(): array
    {
        return ['lexeme:'.$this->lexemeId, 'job:enrich-lexeme-associations'];
    }

    public function uniqueId(): string
    {
        return 'EnrichLexemeAssociations:'.$this->lexemeId;
    }

    public function handle(LexemeEnrichmentService $service): void
    {
        if (! AiConfig::isEnabled() || ! config('ai.lexeme_relations_enrichment.enabled', false)) {
            return;
        }

        try {
            $service->enrichTopRelated(
                $this->lexemeId,
                (int) config('ai.lexeme_relations_enrichment.max_per_lexeme', 5)
            );
        } catch (AiClientException $e) {
            Log::warning('EnrichLexemeAssociationsJob: AI client failed', [
                'lexeme_id' => $this->lexemeId,
                'message' => AiErrorMessage::safe($e),
            ]);

            return;
        }
    }
}
