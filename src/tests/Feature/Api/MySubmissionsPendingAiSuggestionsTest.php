<?php

use App\Modules\Ai\Domain\Models\AiAnalysisRun;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\ContentGrammarCandidate;
use App\Modules\Content\Domain\Models\ContentLexemeCandidate;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('my-submissions reports zero pending suggestions for a content with no analysis run', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, [], 'sanctum');

    Content::factory()->create(['origin' => 'user-submitted', 'status' => 'processing', 'created_by' => $user->id]);

    $response = $this->getJson('/api/content/my-submissions')->assertOk();

    expect($response->json('data.0.pending_ai_suggestions_count'))->toBe(0);
});

test('my-submissions counts only pending lexeme and grammar candidates on the latest run', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, [], 'sanctum');

    $content = Content::factory()->create(['origin' => 'user-submitted', 'status' => 'ready', 'created_by' => $user->id]);
    $run = $content->analysisRuns()->create(['status' => AiAnalysisRun::STATUS_COMPLETED]);
    $run->lexemeCandidates()->create(['text' => 'run', 'normalized_text' => 'run', 'type' => 'word', 'status' => ContentLexemeCandidate::STATUS_PENDING]);
    $run->lexemeCandidates()->create(['text' => 'jump', 'normalized_text' => 'jump', 'type' => 'word', 'status' => ContentLexemeCandidate::STATUS_PENDING]);
    $run->lexemeCandidates()->create(['text' => 'eat', 'normalized_text' => 'eat', 'type' => 'word', 'status' => ContentLexemeCandidate::STATUS_APPLIED]);
    $run->grammarCandidates()->create(['title' => 'Present Simple', 'status' => ContentGrammarCandidate::STATUS_PENDING]);
    $run->grammarCandidates()->create(['title' => 'Past Simple', 'status' => ContentGrammarCandidate::STATUS_REJECTED]);

    $response = $this->getJson('/api/content/my-submissions')->assertOk();

    expect($response->json('data.0.pending_ai_suggestions_count'))->toBe(3);
});

test('my-submissions computes independent counts for multiple submissions', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, [], 'sanctum');

    $withPending = Content::factory()->create(['origin' => 'user-submitted', 'status' => 'ready', 'created_by' => $user->id, 'title' => 'Has pending']);
    $run = $withPending->analysisRuns()->create(['status' => AiAnalysisRun::STATUS_COMPLETED]);
    $run->lexemeCandidates()->create(['text' => 'run', 'normalized_text' => 'run', 'type' => 'word', 'status' => ContentLexemeCandidate::STATUS_PENDING]);

    $withoutPending = Content::factory()->create(['origin' => 'user-submitted', 'status' => 'ready', 'created_by' => $user->id, 'title' => 'No pending']);
    $otherRun = $withoutPending->analysisRuns()->create(['status' => AiAnalysisRun::STATUS_COMPLETED]);
    $otherRun->lexemeCandidates()->create(['text' => 'jump', 'normalized_text' => 'jump', 'type' => 'word', 'status' => ContentLexemeCandidate::STATUS_APPLIED]);

    $response = $this->getJson('/api/content/my-submissions')->assertOk();
    $byTitle = collect($response->json('data'))->keyBy('title');

    expect($byTitle['Has pending']['pending_ai_suggestions_count'])->toBe(1)
        ->and($byTitle['No pending']['pending_ai_suggestions_count'])->toBe(0);
});
