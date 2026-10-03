<?php

use App\Modules\Content\Interfaces\Jobs\FetchTranscriptJob;
use App\Modules\Content\Interfaces\Jobs\ProcessContentJob;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\User\Models\User;
use App\Modules\Content\Application\AiAnalysisAutoDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('create content with source text dispatch process job and run sync results in ready and lexemes', function () {
    Queue::fake();

    $content = Content::query()->create([
        'type' => 'article',
        'title' => 'Integration content',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'pending',
        'source_text' => 'One two three. One two.',
    ]);

    ProcessContentJob::dispatch($content->id);

    Queue::assertPushed(ProcessContentJob::class, function (ProcessContentJob $job) {
        $job->handle(app(AiAnalysisAutoDispatchService::class));

        return true;
    });

    $content->refresh();
    expect($content->status)->toBe('ready');
    expect($content->processing_failure_reason)->toBeNull();
    expect($content->lexemes()->count())->toBe(0);
});

test('run process job synchronously without queue fake', function () {
    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'Sync run',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'pending',
        'source_text' => 'Alpha beta gamma',
    ]);

    $job = new ProcessContentJob($content->id);
    $job->handle(app(AiAnalysisAutoDispatchService::class));

    $content->refresh();
    expect($content->status)->toBe('ready');
    expect($content->processing_failure_reason)->toBeNull();
    expect($content->lexemes()->count())->toBe(0);
});

test('song content with source_text processes to ready without tokenizer lexemes', function () {
    $content = Content::query()->create([
        'type' => 'song',
        'title' => 'Test song',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'pending',
        'source_text' => 'Lyrics line one. Line two.',
    ]);

    (new ProcessContentJob($content->id))->handle(app(AiAnalysisAutoDispatchService::class));

    $content->refresh();
    expect($content->status)->toBe('ready');
    expect($content->processing_failure_reason)->toBeNull();
    expect($content->lexemes()->count())->toBe(0);
});

test('book content with source_text processes to ready without tokenizer lexemes', function () {
    $content = Content::query()->create([
        'type' => 'book',
        'title' => 'Book excerpt',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'pending',
        'source_text' => 'First paragraph. Second sentence.',
    ]);

    (new ProcessContentJob($content->id))->handle(app(AiAnalysisAutoDispatchService::class));

    $content->refresh();
    expect($content->status)->toBe('ready');
    expect($content->processing_failure_reason)->toBeNull();
    expect($content->lexemes()->count())->toBe(0);
});

test('grammar content with source_text processes to ready without tokenizer lexemes', function () {
    $content = Content::query()->create([
        'type' => 'grammar',
        'title' => 'Present Simple rule',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'pending',
        'source_text' => 'Rule and examples. I work every day.',
    ]);

    (new ProcessContentJob($content->id))->handle(app(AiAnalysisAutoDispatchService::class));

    $content->refresh();
    expect($content->status)->toBe('ready');
    expect($content->processing_failure_reason)->toBeNull();
    expect($content->lexemes()->count())->toBe(0);
});

test('catalog returns grammar content when filtering by type grammar', function () {
    $content = Content::query()->create([
        'type' => 'grammar',
        'title' => 'Grammar item',
        'language' => 'en',
        'level' => 'A2',
        'origin' => 'curated',
        'status' => 'ready',
    ]);

    $this->getJson('/api/content?type=grammar')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Grammar item');

    $this->getJson('/api/content?type=book')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('youtube url to transcript to process content job results in ready and lexemes catalog and api', function () {
    config()->set('transcripts.youtube.driver', 'supadata');
    config()->set('transcripts.youtube.drivers.supadata.api_key', 'test-key');

    Http::preventStrayRequests();
    Http::fake([
        'https://api.supadata.ai/v1/transcript*' => Http::response([
            'content' => 'Youtube transcript from real provider integration.',
            'lang' => 'en',
            'availableLangs' => ['en'],
        ], 200),
    ]);

    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'YouTube integration video',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'pending',
        'source_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'source_text' => null,
    ]);

    (new FetchTranscriptJob($content->id))->handle(app(\App\Contracts\YoutubeTranscriptFetcherInterface::class));

    $content->refresh();
    expect($content->status)->toBe('ready');
    expect($content->processing_failure_reason)->toBeNull();
    expect($content->lexemes()->count())->toBe(0);

    $this->getJson('/api/content')
        ->assertOk()
        ->assertJsonPath('data.0.title', 'YouTube integration video');

    $this->getJson("/api/content/{$content->id}")
        ->assertOk()
        ->assertJsonPath('content.status', 'ready')
        ->assertJsonPath('content.title', 'YouTube integration video');

    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('user');

    $lexemesResponse = $this->actingAs($user)->getJson("/api/content/{$content->id}/lexemes")->assertOk();
    expect($lexemesResponse->json('lexemes'))->toBeArray();
    expect(count($lexemesResponse->json('lexemes')))->toBe(0);
});

test('my submissions API shows ingestion states and failure reason', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, [], 'sanctum');

    Content::factory()->create([
        'origin' => 'user-submitted',
        'status' => 'pending',
        'created_by' => $user->id,
    ]);
    Content::factory()->create([
        'origin' => 'user-submitted',
        'status' => 'processing',
        'created_by' => $user->id,
    ]);
    Content::factory()->create([
        'origin' => 'user-submitted',
        'status' => 'failed',
        'processing_failure_reason' => 'Transcript service unavailable.',
        'created_by' => $user->id,
    ]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/content/my-submissions');

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toHaveCount(3);
    expect($data[2]['status'])->toBe('failed');
    expect($data[2]['processing_failure_reason'])->toBe('Transcript service unavailable.');
});

test('process content job is safe to run twice', function () {
    $content = Content::query()->create([
        'type' => 'article',
        'title' => 'Retry safety',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'pending',
        'source_text' => 'Retry retry',
    ]);

    $job = new ProcessContentJob($content->id);
    $job->handle(app(AiAnalysisAutoDispatchService::class));
    $job->handle(app(AiAnalysisAutoDispatchService::class));

    $content->refresh();
    expect($content->status)->toBe('ready');
    expect($content->processing_failure_reason)->toBeNull();
    expect($content->lexemes()->count())->toBe(0);
});
