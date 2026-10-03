<?php

use App\Modules\Ai\Application\AiContentResetService;
use App\Modules\Ai\Domain\Models\AiAnalysisRun;
use App\Modules\Ai\Interfaces\Jobs\RunAiContentAnalysisJob;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\ContentLexeme;
use App\Modules\Content\Interfaces\Jobs\ProcessContentJob;
use Illuminate\Support\Facades\Queue;

function contentForReset(): Content
{
    return Content::query()->create([
        'type' => 'youtube',
        'title' => 'Reset test',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
        'source_text' => 'Transcript for another analysis.',
    ]);
}

test('history reset deletes old runs and starts a fresh analysis', function () {
    config(['ai.enabled' => true]);
    Queue::fake();
    $content = contentForReset();
    $content->analysisRuns()->create(['status' => AiAnalysisRun::STATUS_COMPLETED, 'config' => []]);

    $result = app(AiContentResetService::class)->resetAndRerun($content->id, AiContentResetService::MODE_HISTORY);

    expect($result)->toMatchArray([
        'mode' => AiContentResetService::MODE_HISTORY,
        'deleted_runs' => 1,
        'deleted_content_lexemes' => 0,
    ]);
    expect($content->analysisRuns()->count())->toBe(1);
    Queue::assertPushed(RunAiContentAnalysisJob::class);
});

test('AI results reset removes unlearned AI content words but keeps curated words', function () {
    config(['ai.enabled' => false]);
    Queue::fake();
    $content = contentForReset();
    $content->lexemes()->create(['type' => ContentLexeme::TYPE_WORD, 'text' => 'one', 'sort_order' => 1, 'origin' => ContentLexeme::ORIGIN_AI]);
    $content->lexemes()->create(['type' => ContentLexeme::TYPE_WORD, 'text' => 'two', 'sort_order' => 2, 'origin' => ContentLexeme::ORIGIN_MANUAL]);

    $result = app(AiContentResetService::class)->resetAndRerun($content->id, AiContentResetService::MODE_AI_RESULTS);

    expect($result['deleted_content_lexemes'])->toBe(1);
    expect($content->lexemes()->pluck('text')->all())->toBe(['two']);
});

test('full reset deletes content words and requests content processing', function () {
    Queue::fake();
    $content = contentForReset();
    $content->lexemes()->create(['type' => ContentLexeme::TYPE_WORD, 'text' => 'one', 'sort_order' => 1]);

    $result = app(AiContentResetService::class)->resetAndRerun($content->id, AiContentResetService::MODE_FULL_CONTENT);

    expect($result['deleted_content_lexemes'])->toBe(1);
    expect($content->lexemes()->count())->toBe(0);
    expect($content->fresh()->status)->toBe('pending');
    Queue::assertPushed(ProcessContentJob::class);
});

test('active analysis prevents a reset', function () {
    $content = contentForReset();
    $content->analysisRuns()->create(['status' => AiAnalysisRun::STATUS_RUNNING, 'config' => []]);

    expect(fn () => app(AiContentResetService::class)->resetAndRerun($content->id, AiContentResetService::MODE_HISTORY))
        ->toThrow(RuntimeException::class, 'already in progress');
});
