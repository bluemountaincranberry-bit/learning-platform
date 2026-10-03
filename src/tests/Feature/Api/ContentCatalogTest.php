<?php

use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\ContentLexeme;
use App\Modules\Content\Interfaces\Jobs\FetchTranscriptJob;
use App\Modules\Srs\Domain\Models\SrsCard;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

test('categories endpoint returns supported content categories', function () {
    $this->getJson('/api/content/categories')
        ->assertOk()
        ->assertJsonStructure(['categories']);
});

test('catalog supports filtering by type, language, and level', function () {
    Content::query()->create([
        'type' => 'youtube',
        'title' => 'A2 Video',
        'language' => 'en',
        'level' => 'A2',
        'origin' => 'curated',
        'status' => 'ready',
    ]);

    Content::query()->create([
        'type' => 'book',
        'title' => 'B2 Book',
        'language' => 'en',
        'level' => 'B2',
        'origin' => 'curated',
        'status' => 'ready',
    ]);

    $this->getJson('/api/content?type=youtube&language=en&level=A2')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'A2 Video');
});

test('catalog scope=mine restricts results to the authenticated user own submissions', function () {
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('user');
    $other = User::factory()->create();
    $other->assignRole('user');

    Content::query()->create([
        'type' => 'youtube',
        'title' => 'Mine',
        'language' => 'en',
        'origin' => 'user-submitted',
        'status' => 'ready',
        'created_by' => $user->id,
    ]);

    Content::query()->create([
        'type' => 'youtube',
        'title' => 'Someone else\'s',
        'language' => 'en',
        'origin' => 'user-submitted',
        'status' => 'ready',
        'created_by' => $other->id,
    ]);

    $response = $this->actingAs($user)->getJson('/api/content?scope=mine')->assertOk();
    $titles = collect($response->json('data'))->pluck('title');
    expect($titles)->toContain('Mine');
    expect($titles)->not->toContain("Someone else's");
});

test('catalog scope=mine is ignored for guests', function () {
    Content::query()->create([
        'type' => 'youtube',
        'title' => 'Curated Item',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
    ]);

    $this->getJson('/api/content?scope=mine')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Curated Item');
});

test('submit youtube requires authentication', function () {
    $this->postJson('/api/content/submit-youtube', [
        'title' => 'Submitted video',
        'source_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'language' => 'en',
    ])->assertUnauthorized();
});

test('authenticated user can submit youtube content', function () {
    Queue::fake();

    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

    $user = User::factory()->create();
    $user->assignRole('user');

    $this->actingAs($user)
        ->postJson('/api/content/submit-youtube', [
            'title' => 'Submitted video',
            'source_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'language' => 'en',
        ])
        ->assertCreated()
        ->assertJsonPath('content.status', 'pending')
        ->assertJsonPath('content.origin', 'user-submitted')
        ->assertJsonPath('content.type', 'youtube');

    expect(Content::query()->where('title', 'Submitted video')->exists())->toBeTrue();
    Queue::assertPushed(FetchTranscriptJob::class, 1);
});

test('submit youtube without a title auto-fills it from the video via oEmbed', function () {
    Queue::fake();
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('user');

    Http::fake([
        'https://www.youtube.com/oembed*' => Http::response(['title' => 'Fetched Video Title'], 200),
    ]);

    $this->actingAs($user)
        ->postJson('/api/content/submit-youtube', [
            'source_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'language' => 'en',
        ])
        ->assertCreated()
        ->assertJsonPath('content.title', 'Fetched Video Title');
});

test('submit youtube without a title falls back to a generic title when oEmbed fails', function () {
    Queue::fake();
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('user');

    Http::fake([
        'https://www.youtube.com/oembed*' => Http::response(null, 404),
    ]);

    $this->actingAs($user)
        ->postJson('/api/content/submit-youtube', [
            'source_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'language' => 'en',
        ])
        ->assertCreated()
        ->assertJsonPath('content.title', 'YouTube video');
});

test('submit youtube validates required fields and url format', function () {
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('user');

    $this->actingAs($user)
        ->postJson('/api/content/submit-youtube', [
            'title' => '',
            'source_url' => 'https://example.com/not-youtube',
            'language' => 'en',
        ])
        ->assertStatus(422);
});

test('duplicate youtube submission by same user is rejected', function () {
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

    $user = User::factory()->create();
    $user->assignRole('user');

    $payload = [
        'title' => 'Submitted video',
        'source_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'language' => 'en',
    ];

    $this->actingAs($user)->postJson('/api/content/submit-youtube', $payload)->assertCreated();
    $this->actingAs($user)->postJson('/api/content/submit-youtube', $payload)->assertStatus(422);
});

test('content details endpoint returns ready item', function () {
    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'Detail Video',
        'language' => 'en',
        'level' => 'A1',
        'origin' => 'curated',
        'status' => 'ready',
    ]);

    $this->getJson("/api/content/{$content->id}")
        ->assertOk()
        ->assertJsonPath('content.title', 'Detail Video');
});

test('moderation status controls catalog visibility', function () {
    $ready = Content::query()->create([
        'type' => 'youtube',
        'title' => 'Ready Item',
        'language' => 'en',
        'origin' => 'user-submitted',
        'status' => 'ready',
    ]);

    Content::query()->create([
        'type' => 'youtube',
        'title' => 'Rejected Item',
        'language' => 'en',
        'origin' => 'user-submitted',
        'status' => 'rejected',
    ]);

    $response = $this->getJson('/api/content')->assertOk();
    $titles = collect($response->json('data'))->pluck('title');

    expect($titles)->toContain('Ready Item');
    expect($titles)->not->toContain('Rejected Item');

    $this->getJson("/api/content/{$ready->id}")
        ->assertOk()
        ->assertJsonPath('content.status', 'ready');
});

test('my submissions requires auth and returns only current user submissions', function () {
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

    $this->getJson('/api/content/my-submissions')->assertUnauthorized();

    $user = User::factory()->create();
    $user->assignRole('user');

    Content::query()->create([
        'type' => 'youtube',
        'title' => 'My Video',
        'language' => 'en',
        'origin' => 'user-submitted',
        'status' => 'pending',
        'source_url' => 'https://www.youtube.com/watch?v=abc123',
        'created_by' => $user->id,
    ]);

    $other = User::factory()->create();
    $other->assignRole('user');
    Content::query()->create([
        'type' => 'youtube',
        'title' => 'Other Video',
        'language' => 'en',
        'origin' => 'user-submitted',
        'status' => 'pending',
        'source_url' => 'https://www.youtube.com/watch?v=xyz789',
        'created_by' => $other->id,
    ]);

    $response = $this->actingAs($user)->getJson('/api/content/my-submissions')->assertOk();
    $titles = collect($response->json('data'))->pluck('title');
    expect($titles)->toContain('My Video');
    expect($titles)->not->toContain('Other Video');
});

test('catalog as guest does not include progress fields', function () {
    Content::query()->create([
        'type' => 'youtube',
        'title' => 'No Progress',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
    ]);

    $response = $this->getJson('/api/content')->assertOk();
    $item = $response->json('data.0');
    expect($item)->not->toHaveKey('learned_count');
    expect($item)->not->toHaveKey('total_lexemes');
    expect($item)->not->toHaveKey('progress_pct');
});

test('catalog as auth user includes progress and reflects learned lexemes', function () {
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('user');

    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'Progress Video',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
    ]);
    $lex1 = $content->lexemes()->create(['type' => ContentLexeme::TYPE_WORD, 'text' => 'one', 'sort_order' => 1]);
    $content->lexemes()->create(['type' => ContentLexeme::TYPE_WORD, 'text' => 'two', 'sort_order' => 2]);
    $content->lexemes()->create(['type' => ContentLexeme::TYPE_WORD, 'text' => 'three', 'sort_order' => 3]);

    $response = $this->actingAs($user)->getJson('/api/content')->assertOk();
    $item = collect($response->json('data'))->firstWhere('id', $content->id);
    expect($item)->toHaveKey('learned_count', 0);
    expect($item)->toHaveKey('total_lexemes', 3);
    expect($item)->toHaveKey('progress_pct', 0.0);

    $this->actingAs($user)->postJson("/api/content/lexemes/{$lex1->id}/mark-learned")->assertOk();

    $response = $this->actingAs($user)->getJson('/api/content')->assertOk();
    $item = collect($response->json('data'))->firstWhere('id', $content->id);
    expect($item['learned_count'])->toBe(1);
    expect($item['in_learning_count'])->toBe(0);
    expect($item['total_lexemes'])->toBe(3);
    expect($item['progress_pct'])->toBe(33.3);

    SrsCard::query()->create([
        'user_id' => $user->id,
        'content_id' => $content->id,
        'item_key' => 'word:one',
    ]);

    $response = $this->actingAs($user)->getJson('/api/content')->assertOk();
    $item = collect($response->json('data'))->firstWhere('id', $content->id);
    expect($item['in_learning_count'])->toBe(1);
});

test('catalog as auth user includes ready_to_watch once the exam is passed', function () {
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('user');

    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'Readiness Video',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
    ]);
    $content->lexemes()->create(['type' => ContentLexeme::TYPE_WORD, 'text' => 'one', 'sort_order' => 1]);

    $response = $this->actingAs($user)->getJson('/api/content')->assertOk();
    $item = collect($response->json('data'))->firstWhere('id', $content->id);
    expect($item['ready_to_watch'])->toBeFalse();

    app(App\Modules\Content\Actions\RecordContentExamAttempt::class)->execute($user->id, $content, [
        ['prompt_sentence' => 'A', 'prompt_language' => 'ru', 'answer_language' => 'en', 'answer' => 'a', 'correct' => true],
    ]);

    $response = $this->actingAs($user)->getJson('/api/content')->assertOk();
    $item = collect($response->json('data'))->firstWhere('id', $content->id);
    expect($item['ready_to_watch'])->toBeTrue();
});

test('content detail as auth user includes progress', function () {
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('user');

    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'Detail Progress',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
    ]);
    $lex1 = $content->lexemes()->create(['type' => ContentLexeme::TYPE_WORD, 'text' => 'a', 'sort_order' => 1]);
    $content->lexemes()->create(['type' => ContentLexeme::TYPE_WORD, 'text' => 'b', 'sort_order' => 2]);

    $response = $this->actingAs($user)->getJson("/api/content/{$content->id}")->assertOk();
    expect($response->json('content.learned_count'))->toBe(0);
    expect($response->json('content.in_learning_count'))->toBe(0);
    expect($response->json('content.total_lexemes'))->toBe(2);
    expect($response->json('content.progress_pct'))->toEqual(0.0);

    $this->actingAs($user)->postJson("/api/content/lexemes/{$lex1->id}/mark-learned")->assertOk();

    $response = $this->actingAs($user)->getJson("/api/content/{$content->id}")->assertOk();
    expect($response->json('content.learned_count'))->toBe(1);
    expect($response->json('content.in_learning_count'))->toBe(0);
    expect($response->json('content.total_lexemes'))->toBe(2);
    expect($response->json('content.progress_pct'))->toEqual(50.0);
});
