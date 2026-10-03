<?php

namespace App\Modules\Content\Interfaces\Jobs;

use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Events\ContentPublished;
use App\Modules\Content\Application\AiAnalysisAutoDispatchService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessContentJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function __construct(public int $contentId) {}

    public function tags(): array
    {
        return ['content:'.$this->contentId, 'job:process-content'];
    }

    public function uniqueId(): string
    {
        return 'ProcessContent:'.$this->contentId;
    }

    public function handle(AiAnalysisAutoDispatchService $autoAnalysis): void
    {
        $content = DB::transaction(function () {
            $content = Content::query()->lockForUpdate()->find($this->contentId);

            if (! $content || ! in_array($content->status, ['pending', 'processing'], true)) {
                return null;
            }

            $content->update(['status' => 'processing', 'processing_failure_reason' => null]);

            return $content;
        });

        if (! $content) {
            return;
        }

        try {
            $text = trim(preg_replace('/\s+/', ' ', $content->source_text ?? ''));
            $content->lexemes()->delete();

            if ($text === '') {
                $content->update([
                    'status' => 'ready',
                    'processing_failure_reason' => null,
                    'processing_completed_at' => now(),
                    'analysis_stale_at' => null,
                ]);
                ContentPublished::dispatch($content->id);
                \App\Modules\Infrastructure\Domain\Models\OutboxEvent::record(ContentPublished::class, 'content', $content->id, ['content_id' => $content->id]);

                return;
            }

            $content->update([
                'status' => 'ready',
                'processing_failure_reason' => null,
                'processing_completed_at' => now(),
                'analysis_stale_at' => null,
            ]);
            ContentPublished::dispatch($content->id);
            \App\Modules\Infrastructure\Domain\Models\OutboxEvent::record(ContentPublished::class, 'content', $content->id, ['content_id' => $content->id]);

            try {
                $autoAnalysis->dispatchFor($content);
            } catch (\Throwable $e) {
                Log::warning('Auto AI analysis dispatch failed', [
                    'content_id' => $content->id,
                    'message' => $e->getMessage(),
                ]);
            }
        } catch (\Throwable $e) {
            $content->update([
                'status' => 'failed',
                'processing_failure_reason' => $e->getMessage(),
                'processing_completed_at' => now(),
            ]);

            throw $e;
        }
    }
}
