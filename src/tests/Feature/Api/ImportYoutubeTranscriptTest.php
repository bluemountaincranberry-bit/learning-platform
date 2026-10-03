<?php

namespace Tests\Feature\Api;

use App\Modules\Content\Interfaces\Jobs\ProcessContentJob;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('extension transcript import stores segments and starts processing', function () {
    Queue::fake();
    $user = User::factory()->create();
    Sanctum::actingAs($user, [], 'sanctum');

    $response = $this->postJson('/api/content/import-youtube-transcript', [
        'source_url' => 'https://www.youtube.com/watch?v=abc123',
        'language' => 'en',
        'title' => 'Imported video',
        'segments' => [
            ['start_ms' => 0, 'end_ms' => 1200, 'text' => 'Hello from YouTube.'],
            ['start_ms' => 1200, 'end_ms' => 2400, 'text' => 'Welcome back.'],
        ],
    ]);

    $response->assertCreated()->assertJsonPath('content.title', 'Imported video');
    $content = Content::query()->findOrFail($response->json('content.id'));
    expect($content->source_text)->toBe('Hello from YouTube. Welcome back.')
        ->and($content->transcriptSegments()->count())->toBe(2);
    Queue::assertPushed(ProcessContentJob::class, 1);
});

test('extension transcript import rejects the same video twice for one user', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, [], 'sanctum');
    $payload = [
        'source_url' => 'https://www.youtube.com/watch?v=duplicate123',
        'language' => 'en',
        'segments' => [['start_ms' => 0, 'text' => 'One line.']],
    ];

    $this->postJson('/api/content/import-youtube-transcript', $payload)->assertCreated();
    $this->postJson('/api/content/import-youtube-transcript', $payload)->assertStatus(422);
});
