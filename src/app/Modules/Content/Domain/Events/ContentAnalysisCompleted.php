<?php
namespace App\Modules\Content\Domain\Events;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
final class ContentAnalysisCompleted
{
    use Dispatchable, SerializesModels;
    public function __construct(public int $contentId, public int $analysisRunId) {}
}
