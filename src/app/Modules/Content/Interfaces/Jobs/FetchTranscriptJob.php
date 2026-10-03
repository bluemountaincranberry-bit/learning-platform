<?php

namespace App\Modules\Content\Interfaces\Jobs;

use App\Contracts\YoutubeTranscriptFetcherInterface;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Application\Transcript\TranscriptSegmentStore;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class FetchTranscriptJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function __construct(public int $contentId) {}

    public function tags(): array
    {
        return ['content:'.$this->contentId, 'job:fetch-transcript'];
    }

    public function handle(YoutubeTranscriptFetcherInterface $fetcher, ?TranscriptSegmentStore $segmentStore = null): void
    {
        $segmentStore ??= app(TranscriptSegmentStore::class);
        $content = Content::query()->find($this->contentId);

        if (! $content || ! in_array($content->status, ['pending', 'processing', 'failed'], true)) {
            return;
        }

        $url = trim((string) $content->source_url);
        if ($url === '' || $content->type !== 'youtube') {
            $content->update([
                'status' => 'failed',
                'processing_failure_reason' => $url === ''
                    ? 'Processing failed: missing YouTube source URL.'
                    : 'Processing failed: transcript fetch is only supported for YouTube content.',
                'processing_completed_at' => now(),
            ]);

            return;
        }

        try {
            $document = $fetcher->fetch($url, trim((string) $content->language) !== '' ? $content->language : null);
            $shouldDispatch = false;

            DB::transaction(function () use ($document, $segmentStore, &$shouldDispatch): void {
                $locked = Content::query()->lockForUpdate()->find($this->contentId);
                if (! $locked || ! in_array($locked->status, ['pending', 'processing', 'failed'], true)) {
                    return;
                }

                $hadTranscript = trim((string) $locked->source_text) !== '' || $locked->transcriptSegments()->exists();
                $locked->update([
                    'status' => 'processing',
                    'source_text' => $document->fullText,
                    'transcript_accepted_at' => null,
                    'transcript_accepted_by' => null,
                    'analysis_stale_at' => null,
                    'processing_failure_reason' => null,
                ]);
                $segmentStore->replace($locked, $document);
                $shouldDispatch = ! $hadTranscript;
            });

            if ($shouldDispatch) {
                ProcessContentJob::dispatch($this->contentId);
            }
        } catch (\Throwable $e) {
            DB::transaction(function () use ($e): void {
                $locked = Content::query()->lockForUpdate()->find($this->contentId);
                if (! $locked || ! in_array($locked->status, ['pending', 'processing'], true)) {
                    return;
                }
                $locked->update([
                    'status' => 'failed',
                    'processing_failure_reason' => $e->getMessage(),
                    'processing_completed_at' => now(),
                ]);
            });

            throw $e;
        }
    }
}
