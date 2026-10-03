<?php

use App\Modules\Content\Actions\AcceptTranscript;
use App\Modules\Content\Application\AiAnalysisAutoDispatchService;
use App\Modules\Content\Application\Contracts\ContentProcessingOrchestratorInterface;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\ContentLexeme;
use App\Modules\Content\Interfaces\Jobs\ProcessContentJob;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('content can mark transcript as accepted by an admin user', function () {
    $admin = User::factory()->create();
    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'Accepted transcript',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
        'source_text' => 'Transcript text.',
    ]);

    $content->markTranscriptAccepted($admin->id);

    $content->refresh();
    expect($content->transcript_accepted_at)->not->toBeNull();
    expect($content->transcript_accepted_by)->toBe($admin->id);
    expect($content->isTranscriptAccepted())->toBeTrue();
});

test('accept transcript action rejects empty transcripts without changing acceptance', function () {
    $admin = User::factory()->create();
    $content = Content::factory()->create([
        'source_text' => '   ',
        'transcript_accepted_at' => null,
    ]);

    expect(fn () => app(AcceptTranscript::class)->execute($content, $admin->id))
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    expect($content->fresh()->transcript_accepted_at)->toBeNull();
});

test('changing a processed transcript clears acceptance and marks analysis stale', function () {
    $admin = User::factory()->create();
    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'Processed transcript',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
        'source_text' => 'Original transcript.',
        'transcript_accepted_at' => now(),
        'transcript_accepted_by' => $admin->id,
    ]);
    $content->lexemes()->create([
        'type' => ContentLexeme::TYPE_WORD,
        'text' => 'original',
        'sort_order' => 1,
    ]);

    $attributes = $content->transcriptChangedAttributes('Updated transcript.');

    expect($attributes['transcript_accepted_at'])->toBeNull();
    expect($attributes['transcript_accepted_by'])->toBeNull();
    expect($attributes['analysis_stale_at'])->not->toBeNull();
});

test('stale ready content can be submitted for processing again', function () {
    Queue::fake();

    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'Stale transcript',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
        'source_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'source_text' => 'Updated transcript.',
        'analysis_stale_at' => now(),
    ]);

    $result = app(ContentProcessingOrchestratorInterface::class)->request($content);

    expect($result->accepted)->toBeTrue();

    $content->refresh();
    expect($content->status)->toBe('pending');
    expect($content->processing_requested_at)->not->toBeNull();
    Queue::assertPushed(ProcessContentJob::class, 1);
});

test('successful processing clears stale analysis marker', function () {
    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'Reprocessed transcript',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'pending',
        'source_text' => 'Fresh transcript.',
        'analysis_stale_at' => now(),
    ]);

    (new ProcessContentJob($content->id))->handle(app(AiAnalysisAutoDispatchService::class));

    $content->refresh();
    expect($content->status)->toBe('ready');
    expect($content->analysis_stale_at)->toBeNull();
});

test('accepting the same transcript twice preserves its content and owner', function () {
    $admin = User::factory()->create();
    $content = Content::factory()->create(['source_text' => 'The same transcript.']);

    $content->markTranscriptAccepted($admin->id);
    $content->markTranscriptAccepted($admin->id);

    expect($content->fresh()->source_text)->toBe('The same transcript.')
        ->and($content->fresh()->transcript_accepted_by)->toBe($admin->id);
});

test('transcript acceptance is rolled back with its surrounding transaction', function () {
    $admin = User::factory()->create();
    $content = Content::factory()->create(['transcript_accepted_at' => null]);

    expect(fn () => \Illuminate\Support\Facades\DB::transaction(function () use ($content, $admin) {
        $content->markTranscriptAccepted($admin->id);
        throw new RuntimeException('Simulated acceptance failure');
    }))->toThrow(RuntimeException::class, 'Simulated acceptance failure');

    expect($content->fresh()->transcript_accepted_at)->toBeNull();
});
