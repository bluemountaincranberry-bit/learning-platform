<?php

namespace App\Modules\Content\Interfaces\Jobs;

use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Application\Transcript\TranscriptTranslationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TranslateTranscriptSegmentsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 180;

    public function __construct(public int $contentId, public string $language) {}

    public function uniqueId(): string
    {
        return "TranslateTranscript:{$this->contentId}:{$this->language}";
    }

    public function handle(TranscriptTranslationService $service): void
    {
        $content = Content::query()->find($this->contentId);
        if ($content === null) {
            return;
        }

        $service->getOrCreate($content, $this->language);
    }
}
