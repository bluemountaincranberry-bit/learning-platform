<?php

namespace Tests\Feature\Api;

use App\Modules\Content\Domain\Models\Content;
use App\Contracts\Ai\AiJsonClient;
use App\Modules\Content\Application\Transcript\TranscriptDocument;
use App\Modules\Content\Application\Transcript\TranscriptSegmentData;
use App\Modules\Content\Application\Transcript\TranscriptSegmentStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('transcript segments are stored with timestamps and linked to lexeme occurrences', function () {
    $content = Content::factory()->create([
        'source_text' => 'I am waiting here.',
    ]);
    $lexeme = $content->lexemes()->create([
        'type' => 'word',
        'text' => 'waiting',
        'sort_order' => 1,
    ]);

    $document = new TranscriptDocument(
        fullText: 'I am waiting here.',
        language: 'en',
        source: 'youtube',
        segments: [
            new TranscriptSegmentData(1200, 3400, 'I am waiting here.', 'cue-1'),
        ],
    );

    $store = app(TranscriptSegmentStore::class);
    $store->replace($content, $document);
    $store->linkLexemes($content->fresh());

    $this->getJson('/api/content/'.$content->id.'/transcript')
        ->assertOk()
        ->assertJsonPath('segments.0.start_ms', 1200)
        ->assertJsonPath('segments.0.end_ms', 3400)
        ->assertJsonPath('segments.0.lexemes.0.content_lexeme_id', $lexeme->id)
        ->assertJsonPath('segments.0.lexemes.0.surface_text', 'waiting');
});

test('transcript endpoint can limit segments to a playback range', function () {
    $content = Content::factory()->create();
    $content->transcriptSegments()->createMany([
        ['sequence' => 0, 'start_ms' => 0, 'end_ms' => 1000, 'text' => 'First'],
        ['sequence' => 1, 'start_ms' => 3000, 'end_ms' => 4000, 'text' => 'Second'],
    ]);

    $this->getJson('/api/content/'.$content->id.'/transcript?from_ms=2000&to_ms=5000')
        ->assertOk()
        ->assertJsonCount(1, 'segments')
        ->assertJsonPath('segments.0.text', 'Second');
});

test('native transcript translations are cached per language', function () {
    $content = Content::factory()->create();
    $segment = $content->transcriptSegments()->create([
        'sequence' => 0,
        'start_ms' => 0,
        'end_ms' => 1000,
        'text' => 'Hello there',
    ]);

    $client = \Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andReturn([
        'translations' => [['id' => $segment->id, 'text' => 'Привет']],
    ]);
    app()->instance(AiJsonClient::class, $client);

    $service = app(\App\Modules\Content\Application\Transcript\TranscriptTranslationService::class);
    expect($service->getOrCreate($content, 'ru'))->toBe([$segment->id => 'Привет']);
    expect($service->getOrCreate($content, 'ru'))->toBe([$segment->id => 'Привет']);
});

test('supadata preserves sub-second millisecond offsets without converting them twice', function () {
    Http::fake([
        'https://api.supadata.ai/v1/transcript*' => Http::response([
            'content' => [
                ['text' => 'Hello', 'offset' => 500, 'duration' => 400],
                ['text' => 'there', 'offset' => 900, 'duration' => 1200],
            ],
        ]),
    ]);

    $document = app(\App\Modules\Content\Infrastructure\Integrations\SupadataYoutubeTranscriptFetcher::class, [
        'baseUrl' => 'https://api.supadata.ai/v1',
        'apiKey' => 'test-key',
    ])->fetch('https://www.youtube.com/watch?v=abcdefghijk', 'en');

    expect($document->segments[0]->startMs)->toBe(500)
        ->and($document->segments[0]->endMs)->toBe(900)
        ->and($document->segments[1]->startMs)->toBe(900)
        ->and($document->segments[1]->endMs)->toBe(2100);
});
