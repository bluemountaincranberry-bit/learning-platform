<?php

namespace App\Modules\Content\Actions;

use App\Modules\Content\Application\Contracts\ContentProcessingOrchestratorInterface;
use App\Modules\Content\Application\Data\ContentProcessingRequestResult;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Interfaces\Jobs\FetchTranscriptJob;
use App\Modules\Content\Interfaces\Jobs\ProcessContentJob;

final class RequestContentProcessing implements ContentProcessingOrchestratorInterface
{
    public function request(Content $content): ContentProcessingRequestResult
    {
        $sourceText = trim((string) ($content->source_text ?? ''));
        $hasStaleAnalysis = $content->analysis_stale_at !== null;

        if (in_array($content->type, ['song', 'book', 'grammar', 'movie'], true) && $sourceText === '') {
            return ContentProcessingRequestResult::blocked(
                'Song, book, movie and grammar content require source text before processing'
            );
        }

        if ($content->status !== 'pending' && ! $content->canTransitionTo('pending') && ! ($content->status === 'ready' && $hasStaleAnalysis)) {
            return ContentProcessingRequestResult::blocked('Content cannot be submitted for processing.');
        }

        if ($content->status !== 'pending') {
            $content->update([
                'status' => 'pending',
                'processing_failure_reason' => null,
                'processing_requested_at' => now(),
                'processing_completed_at' => null,
            ]);
            $content->refresh();
        } else {
            $content->update([
                'processing_failure_reason' => null,
                'processing_requested_at' => now(),
                'processing_completed_at' => null,
            ]);
            $content->refresh();
        }

        if ($content->type === 'youtube' && $sourceText === '') {
            FetchTranscriptJob::dispatch($content->id);

            return ContentProcessingRequestResult::accepted();
        }

        ProcessContentJob::dispatch($content->id);

        return ContentProcessingRequestResult::accepted();
    }
}
