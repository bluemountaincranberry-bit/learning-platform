<?php

use App\Modules\Ai\Interfaces\Jobs\EnrichLexemeAssociationsJob;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\ContentLexeme;
use App\Modules\Content\Domain\Models\Lexeme;
use App\Modules\Content\Domain\Models\LexemeExample;
use App\Modules\User\Models\User;
use App\Contracts\Ai\AiJsonClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
});

function makeTranscriptLexemeUser(): User
{
    $user = User::factory()->create(['translation_language' => 'ru']);
    $user->assignRole('user');

    return $user;
}

/**
 * Task 10.6: analyzeForManualAdd() now makes one richer completeJson() call
 * (lemma/part_of_speech/sense/translation/grammar/level/example_translation)
 * instead of the old bare {translation} shape — this is the fake response
 * for that call.
 */
function fakeManualAddAnalysis(array $overrides = []): void
{
    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andReturn([...[
        'lemma' => 'hello',
        'part_of_speech' => 'interjection',
        'translation' => 'Привет',
        'level' => 'A1',
        'example_translation' => 'Привет, как дела?',
    ], ...$overrides]);
    app()->instance(AiJsonClient::class, $client);
}

test('creating a transcript lexeme requires auth', function () {
    $content = Content::factory()->create(['status' => 'ready']);
    $segment = $content->transcriptSegments()->create(['sequence' => 0, 'start_ms' => 0, 'end_ms' => 1000, 'text' => 'Hello there']);

    $this->postJson("/api/content/{$content->id}/transcript/lexemes", [
        'transcript_segment_id' => $segment->id,
        'text' => 'Hello',
        'start_offset' => 0,
        'end_offset' => 5,
    ])->assertUnauthorized();
});

test('creates a fully enriched content lexeme through the same pipeline as an AI-extracted candidate (task 10.6)', function () {
    Config::set('ai.enabled', true);
    Queue::fake();
    fakeManualAddAnalysis();

    $user = makeTranscriptLexemeUser();
    $content = Content::factory()->create(['status' => 'ready', 'language' => 'en']);
    $segment = $content->transcriptSegments()->create(['sequence' => 0, 'start_ms' => 0, 'end_ms' => 1000, 'text' => 'Hello, how are you?']);

    $response = $this->actingAs($user)->postJson("/api/content/{$content->id}/transcript/lexemes", [
        'transcript_segment_id' => $segment->id,
        'text' => 'Hello',
        'start_offset' => 0,
        'end_offset' => 5,
    ])->assertOk()->assertJsonPath('text', 'Hello')->assertJsonPath('translation', 'Привет');

    $lexemeId = $response->json('content_lexeme_id');
    $contentLexeme = ContentLexeme::findOrFail($lexemeId);
    expect($contentLexeme->content_id)->toBe($content->id)
        ->and($contentLexeme->type)->toBe(ContentLexeme::TYPE_WORD)
        ->and($contentLexeme->origin)->toBe(ContentLexeme::ORIGIN_MANUAL)
        ->and($contentLexeme->lexeme_id)->not->toBeNull();

    $canonical = Lexeme::findOrFail($contentLexeme->lexeme_id);
    expect($canonical->lemma)->toBe('hello')
        ->and($canonical->part_of_speech)->toBe('interjection')
        ->and($canonical->level)->toBe('A1');

    $example = LexemeExample::where('lexeme_id', $canonical->id)->first();
    expect($example)->not->toBeNull()
        ->and($example->example)->toBe('Hello, how are you?')
        ->and($example->translation)->toBe('Привет, как дела?');

    // Same auto-enrichment wiring as the AI batch pipeline — a brand-new
    // lemma dispatches relation enrichment too, not just level suggestion.
    Queue::assertPushed(EnrichLexemeAssociationsJob::class, fn ($job) => $job->lexemeId === $canonical->id);

    $this->assertDatabaseHas('transcript_segment_lexemes', [
        'transcript_segment_id' => $segment->id,
        'content_lexeme_id' => $lexemeId,
        'start_offset' => 0,
        'end_offset' => 5,
        'surface_text' => 'Hello',
    ]);
});

test('creates a phrase-type occurrence when the selected text spans multiple words', function () {
    Config::set('ai.enabled', true);
    Queue::fake(); // brand-new lexeme would otherwise synchronously dispatch SuggestLexemeLevelJob (QUEUE_CONNECTION=sync)
    fakeManualAddAnalysis(['lemma' => 'give up', 'part_of_speech' => 'phrase', 'translation' => 'сдаваться']);

    $user = makeTranscriptLexemeUser();
    $content = Content::factory()->create(['status' => 'ready', 'language' => 'en']);
    $segment = $content->transcriptSegments()->create(['sequence' => 0, 'start_ms' => 0, 'end_ms' => 1000, 'text' => 'Never give up on your dreams.']);

    $response = $this->actingAs($user)->postJson("/api/content/{$content->id}/transcript/lexemes", [
        'transcript_segment_id' => $segment->id,
        'text' => 'give up',
        'start_offset' => 6,
        'end_offset' => 13,
    ])->assertOk();

    $contentLexeme = ContentLexeme::findOrFail($response->json('content_lexeme_id'));
    expect($contentLexeme->type)->toBe(ContentLexeme::TYPE_PHRASE);
});

test('matches an already-catalogued lemma instead of creating a duplicate Lexeme', function () {
    Config::set('ai.enabled', true);
    $existing = Lexeme::query()->create([
        'slug' => 'en-hello', 'language' => 'en', 'lemma' => 'hello', 'normalized_lemma' => 'hello', 'status' => Lexeme::STATUS_PUBLISHED,
    ]);
    fakeManualAddAnalysis();

    $user = makeTranscriptLexemeUser();
    $content = Content::factory()->create(['status' => 'ready', 'language' => 'en']);
    $segment = $content->transcriptSegments()->create(['sequence' => 0, 'start_ms' => 0, 'end_ms' => 1000, 'text' => 'Hello there']);

    $response = $this->actingAs($user)->postJson("/api/content/{$content->id}/transcript/lexemes", [
        'transcript_segment_id' => $segment->id,
        'text' => 'Hello',
        'start_offset' => 0,
        'end_offset' => 5,
    ])->assertOk();

    $contentLexeme = ContentLexeme::findOrFail($response->json('content_lexeme_id'));
    expect($contentLexeme->lexeme_id)->toBe($existing->id);
    expect(Lexeme::where('normalized_lemma', 'hello')->count())->toBe(1);
});

test('reuses an existing content lexeme for the same word instead of duplicating it', function () {
    Config::set('ai.enabled', true);
    $user = makeTranscriptLexemeUser();
    $content = Content::factory()->create(['status' => 'ready', 'language' => 'en']);
    $existing = $content->lexemes()->create(['type' => 'word', 'text' => 'hello', 'sort_order' => 1]);
    $existing->update(['lexeme_id' => Lexeme::query()->firstOrCreate(
        ['language' => 'en', 'normalized_lemma' => 'hello'],
        ['slug' => 'en-hello', 'lemma' => 'hello', 'status' => Lexeme::STATUS_REVIEW]
    )->id]);
    $existing->canonicalLexeme->translations()->create([
        'language' => 'ru', 'translation' => 'Привет', 'is_primary' => true,
    ]);
    $segment = $content->transcriptSegments()->create(['sequence' => 0, 'start_ms' => 0, 'end_ms' => 1000, 'text' => 'Hello there']);

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldNotReceive('completeJson');
    app()->instance(AiJsonClient::class, $client);

    $this->actingAs($user)->postJson("/api/content/{$content->id}/transcript/lexemes", [
        'transcript_segment_id' => $segment->id,
        'text' => 'Hello',
        'start_offset' => 0,
        'end_offset' => 5,
    ])->assertOk()
        ->assertJsonPath('content_lexeme_id', $existing->id)
        ->assertJsonPath('translation', 'Привет');

    expect(ContentLexeme::where('content_id', $content->id)->count())->toBe(1);
});

test('creating a transcript lexeme returns 503 when AI feature is disabled', function () {
    Config::set('ai.enabled', false);
    $user = makeTranscriptLexemeUser();
    $content = Content::factory()->create(['status' => 'ready']);
    $segment = $content->transcriptSegments()->create(['sequence' => 0, 'start_ms' => 0, 'end_ms' => 1000, 'text' => 'Hello there']);

    $this->actingAs($user)->postJson("/api/content/{$content->id}/transcript/lexemes", [
        'transcript_segment_id' => $segment->id,
        'text' => 'Hello',
        'start_offset' => 0,
        'end_offset' => 5,
    ])->assertStatus(503)->assertJsonFragment(['message' => 'AI feature is disabled.']);
});
