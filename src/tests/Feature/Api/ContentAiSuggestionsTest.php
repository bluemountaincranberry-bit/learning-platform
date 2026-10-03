<?php

use App\Modules\Ai\Domain\Models\AiAnalysisRun;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\ContentGrammarCandidate;
use App\Modules\Content\Domain\Models\ContentLexemeCandidate;
use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Domain\Models\Lexeme;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
});

/**
 * @return array{0: Content, 1: AiAnalysisRun}
 */
function makeContentWithPendingRun(int $ownerId, string $language = 'en'): array
{
    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'User video',
        'language' => $language,
        'origin' => 'user-submitted',
        'status' => 'ready',
        'source_text' => 'I like to run and jump every day.',
        'created_by' => $ownerId,
    ]);

    $run = $content->analysisRuns()->create([
        'status' => AiAnalysisRun::STATUS_COMPLETED,
        'config' => ['translation_language' => 'ru'],
    ]);

    return [$content, $run];
}

test('ai-suggestions index requires auth', function () {
    [$content] = makeContentWithPendingRun(User::factory()->create()->id);

    $this->getJson("/api/content/{$content->id}/ai-suggestions")->assertUnauthorized();
});

test('ai-suggestions index returns 403 for a user who does not own the content', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $stranger->assignRole('user');
    [$content, $run] = makeContentWithPendingRun($owner->id);
    $run->lexemeCandidates()->create([
        'text' => 'run', 'normalized_text' => 'run', 'type' => ContentLexemeCandidate::TYPE_WORD,
        'status' => ContentLexemeCandidate::STATUS_PENDING,
    ]);

    $this->actingAs($stranger)
        ->getJson("/api/content/{$content->id}/ai-suggestions")
        ->assertForbidden();
});

test('ai-suggestions index returns pending candidates for the content owner', function () {
    $owner = User::factory()->create();
    $owner->assignRole('user');
    [$content, $run] = makeContentWithPendingRun($owner->id);
    $run->lexemeCandidates()->create([
        'text' => 'run', 'normalized_text' => 'run', 'type' => ContentLexemeCandidate::TYPE_WORD,
        'translation' => 'бегать', 'status' => ContentLexemeCandidate::STATUS_PENDING,
        'examples' => [['text' => 'I run daily.', 'translation' => 'Я бегаю каждый день.', 'source' => 'context']],
    ]);
    $run->lexemeCandidates()->create([
        'text' => 'jump', 'normalized_text' => 'jump', 'type' => ContentLexemeCandidate::TYPE_WORD,
        'status' => ContentLexemeCandidate::STATUS_REJECTED, // must not appear
    ]);
    $run->grammarCandidates()->create([
        'title' => 'Present Simple', 'status' => ContentGrammarCandidate::STATUS_PENDING,
    ]);

    $response = $this->actingAs($owner)
        ->getJson("/api/content/{$content->id}/ai-suggestions")
        ->assertOk();

    $response->assertJson(['run_id' => $run->id]);
    expect($response->json('lexeme_candidates'))->toHaveCount(1)
        ->and($response->json('lexeme_candidates.0.text'))->toBe('run')
        ->and($response->json('lexeme_candidates.0.translation'))->toBe('бегать')
        ->and($response->json('lexeme_candidates.0.examples'))->toBe([['text' => 'I run daily.', 'translation' => 'Я бегаю каждый день.', 'source' => 'context']])
        ->and($response->json('grammar_candidates'))->toHaveCount(1)
        ->and($response->json('grammar_candidates.0.title'))->toBe('Present Simple');
});

test('ai-suggestions index reports applied counts for task 9.4/9.8\'s passive notice', function () {
    $owner = User::factory()->create();
    $owner->assignRole('user');
    [$content, $run] = makeContentWithPendingRun($owner->id);
    $run->lexemeCandidates()->create([
        'text' => 'run', 'normalized_text' => 'run', 'type' => ContentLexemeCandidate::TYPE_WORD,
        'status' => ContentLexemeCandidate::STATUS_APPLIED,
    ]);
    $run->lexemeCandidates()->create([
        'text' => 'walk', 'normalized_text' => 'walk', 'type' => ContentLexemeCandidate::TYPE_WORD,
        'status' => ContentLexemeCandidate::STATUS_APPLIED,
    ]);
    $run->lexemeCandidates()->create([
        'text' => 'maybe', 'normalized_text' => 'maybe', 'type' => ContentLexemeCandidate::TYPE_WORD,
        'status' => ContentLexemeCandidate::STATUS_REJECTED,
    ]);
    $run->grammarCandidates()->create(['title' => 'Present Simple', 'status' => ContentGrammarCandidate::STATUS_APPLIED]);

    $response = $this->actingAs($owner)
        ->getJson("/api/content/{$content->id}/ai-suggestions")
        ->assertOk();

    $response->assertJson([
        'applied_lexeme_count' => 2,
        'applied_grammar_count' => 1,
        'lexeme_candidates' => [],
        'grammar_candidates' => [],
    ]);
});

test('ai-suggestions index returns empty arrays when there is no analysis run yet', function () {
    $owner = User::factory()->create();
    $owner->assignRole('user');
    $content = Content::query()->create([
        'type' => 'youtube', 'title' => 'V', 'language' => 'en', 'origin' => 'user-submitted',
        'status' => 'processing', 'created_by' => $owner->id,
    ]);

    $response = $this->actingAs($owner)
        ->getJson("/api/content/{$content->id}/ai-suggestions")
        ->assertOk();

    $response->assertJson(['run_id' => null, 'lexeme_candidates' => [], 'grammar_candidates' => []]);
});

test('ai-suggestions accept returns 403 for a non-owner and applies nothing', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $stranger->assignRole('user');
    [$content, $run] = makeContentWithPendingRun($owner->id);
    $candidate = $run->lexemeCandidates()->create([
        'text' => 'run', 'normalized_text' => 'run', 'type' => ContentLexemeCandidate::TYPE_WORD,
        'status' => ContentLexemeCandidate::STATUS_PENDING,
    ]);

    $this->actingAs($stranger)
        ->postJson("/api/content/{$content->id}/ai-suggestions/accept", ['accept_all' => true])
        ->assertForbidden();

    expect($candidate->fresh()->status)->toBe(ContentLexemeCandidate::STATUS_PENDING);
});

test('ai-suggestions accept with accept_all applies every pending candidate for the owner', function () {
    $owner = User::factory()->create();
    $owner->assignRole('user');
    [$content, $run] = makeContentWithPendingRun($owner->id);
    $lexemeCandidate = $run->lexemeCandidates()->create([
        'text' => 'run', 'normalized_text' => 'run', 'type' => ContentLexemeCandidate::TYPE_WORD,
        'translation' => 'бегать', 'example' => 'I run every day.',
        'status' => ContentLexemeCandidate::STATUS_PENDING,
    ]);
    $grammarCandidate = $run->grammarCandidates()->create([
        'title' => 'Present Simple', 'summary' => 'Habitual actions.',
        'status' => ContentGrammarCandidate::STATUS_PENDING,
    ]);

    $response = $this->actingAs($owner)
        ->postJson("/api/content/{$content->id}/ai-suggestions/accept", ['accept_all' => true])
        ->assertOk();

    $response->assertJson(['applied' => ['lexemes' => 1, 'grammar' => 1]]);
    expect($lexemeCandidate->fresh()->status)->toBe(ContentLexemeCandidate::STATUS_APPLIED)
        ->and($grammarCandidate->fresh()->status)->toBe(ContentGrammarCandidate::STATUS_APPLIED);

    // Applied through the real, unmodified AiCandidateApplyService — the
    // word lands in the canonical catalog with its translation, not just a
    // flipped candidate status.
    $lexeme = Lexeme::query()->where('normalized_lemma', 'run')->where('language', 'en')->first();
    expect($lexeme)->not->toBeNull();
    expect($lexeme->translations()->where('translation', 'бегать')->exists())->toBeTrue();
    expect(GrammarRule::query()->where('title', 'Present Simple')->exists())->toBeTrue();
});

test('ai-suggestions accept with explicit ids only applies those, leaving the rest pending', function () {
    $owner = User::factory()->create();
    $owner->assignRole('user');
    [$content, $run] = makeContentWithPendingRun($owner->id);
    $toAccept = $run->lexemeCandidates()->create([
        'text' => 'run', 'normalized_text' => 'run', 'type' => ContentLexemeCandidate::TYPE_WORD,
        'status' => ContentLexemeCandidate::STATUS_PENDING,
    ]);
    $toLeave = $run->lexemeCandidates()->create([
        'text' => 'jump', 'normalized_text' => 'jump', 'type' => ContentLexemeCandidate::TYPE_WORD,
        'status' => ContentLexemeCandidate::STATUS_PENDING,
    ]);

    $this->actingAs($owner)
        ->postJson("/api/content/{$content->id}/ai-suggestions/accept", [
            'lexeme_candidate_ids' => [$toAccept->id],
        ])
        ->assertOk();

    expect($toAccept->fresh()->status)->toBe(ContentLexemeCandidate::STATUS_APPLIED)
        ->and($toLeave->fresh()->status)->toBe(ContentLexemeCandidate::STATUS_PENDING);
});

test('ai-suggestions accept returns 422 when nothing is selected', function () {
    $owner = User::factory()->create();
    $owner->assignRole('user');
    [$content] = makeContentWithPendingRun($owner->id);

    $this->actingAs($owner)
        ->postJson("/api/content/{$content->id}/ai-suggestions/accept", [])
        ->assertStatus(422);
});

test('ai-suggestions accept returns 404 when the content has no analysis run', function () {
    $owner = User::factory()->create();
    $owner->assignRole('user');
    $content = Content::query()->create([
        'type' => 'youtube', 'title' => 'V', 'language' => 'en', 'origin' => 'user-submitted',
        'status' => 'processing', 'created_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->postJson("/api/content/{$content->id}/ai-suggestions/accept", ['accept_all' => true])
        ->assertStatus(404);
});

test('reanalyze requires auth', function () {
    [$content] = makeContentWithPendingRun(User::factory()->create()->id);

    $this->postJson("/api/content/{$content->id}/reanalyze")->assertUnauthorized();
});

test('reanalyze returns 403 for a user who does not own the content', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $stranger->assignRole('user');
    [$content] = makeContentWithPendingRun($owner->id);

    $this->actingAs($stranger)
        ->postJson("/api/content/{$content->id}/reanalyze")
        ->assertForbidden();
});

test('reanalyze dispatches a fresh run for the owner', function () {
    config(['ai.enabled' => true]);
    Illuminate\Support\Facades\Queue::fake();
    $owner = User::factory()->create();
    $owner->assignRole('user');
    $content = Content::query()->create([
        'type' => 'youtube', 'title' => 'V', 'language' => 'en', 'origin' => 'curated',
        'status' => 'ready', 'source_text' => 'Some transcript.', 'created_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->postJson("/api/content/{$content->id}/reanalyze")
        ->assertStatus(202)
        ->assertJson(['status' => 'dispatched']);

    expect($content->fresh()->latestAnalysisRun)->not->toBeNull();
});

test('reanalyze returns 409 when a run is already pending', function () {
    config(['ai.enabled' => true]);
    $owner = User::factory()->create();
    $owner->assignRole('user');
    [$content] = makeContentWithPendingRun($owner->id);
    $content->analysisRuns()->create(['status' => AiAnalysisRun::STATUS_PENDING, 'config' => []]);

    $this->actingAs($owner)
        ->postJson("/api/content/{$content->id}/reanalyze")
        ->assertStatus(409);
});

test('reanalyze returns 503 when AI is disabled', function () {
    config(['ai.enabled' => false]);
    $owner = User::factory()->create();
    $owner->assignRole('user');
    [$content] = makeContentWithPendingRun($owner->id);

    $this->actingAs($owner)
        ->postJson("/api/content/{$content->id}/reanalyze")
        ->assertStatus(503);
});
