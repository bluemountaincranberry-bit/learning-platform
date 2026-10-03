<?php

use App\Modules\Ai\Domain\Models\AiAnalysisRun;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
});

function makeReadyContent(): Content
{
    return Content::query()->create([
        'type' => 'youtube', 'title' => 'C', 'language' => 'en', 'origin' => 'curated', 'status' => 'ready',
    ]);
}

test('lexemes endpoint does not flag anything not_analyzed before any analysis run exists', function () {
    $content = makeReadyContent();
    $content->lexemes()->create(['type' => 'word', 'text' => 'run', 'sort_order' => 1]);

    $user = User::factory()->create();
    $user->assignRole('user');

    $response = $this->actingAs($user)->getJson("/api/content/{$content->id}/lexemes")->assertOk();

    expect(collect($response->json('lexemes'))->firstWhere('text', 'run')['not_analyzed'])->toBeFalse();
});

test('lexemes endpoint does not flag anything not_analyzed while the latest run has not completed', function () {
    $content = makeReadyContent();
    $content->lexemes()->create(['type' => 'word', 'text' => 'run', 'sort_order' => 1]);
    $content->analysisRuns()->create(['status' => AiAnalysisRun::STATUS_PENDING]);

    $user = User::factory()->create();
    $user->assignRole('user');

    $response = $this->actingAs($user)->getJson("/api/content/{$content->id}/lexemes")->assertOk();

    expect(collect($response->json('lexemes'))->firstWhere('text', 'run')['not_analyzed'])->toBeFalse();
});

test('lexemes endpoint flags only the words the latest completed run left uncovered', function () {
    // Regression scenario: an AI candidate "give up" was accepted and applied
    // (constituent words "give"/"up" are therefore covered), but "jump" from
    // the transcript was never covered by any candidate.
    $content = makeReadyContent();
    $content->lexemes()->create(['type' => 'word', 'text' => 'give', 'sort_order' => 1]);
    $content->lexemes()->create(['type' => 'word', 'text' => 'up', 'sort_order' => 2]);
    $content->lexemes()->create(['type' => 'word', 'text' => 'jump', 'sort_order' => 3]);
    $content->lexemes()->create(['type' => 'phrasal_verb', 'text' => 'give up', 'sort_order' => 4]);
    $content->analysisRuns()->create([
        'status' => AiAnalysisRun::STATUS_COMPLETED,
        'coverage_pct' => 75.0,
        'uncovered_words' => ['jump'],
    ]);

    $user = User::factory()->create();
    $user->assignRole('user');

    $response = $this->actingAs($user)->getJson("/api/content/{$content->id}/lexemes")->assertOk();
    $lexemes = collect($response->json('lexemes'))->keyBy('text');

    expect($lexemes['give']['not_analyzed'])->toBeFalse()
        ->and($lexemes['up']['not_analyzed'])->toBeFalse()
        ->and($lexemes['jump']['not_analyzed'])->toBeTrue()
        // Never flags a non-word (phrase/phrasal_verb/etc.) AI-native entry —
        // this list is only ever about the tokenizer's plain leftovers.
        ->and($lexemes['give up']['not_analyzed'])->toBeFalse();
});

test('lexemes endpoint flags nothing not_analyzed when the latest completed run covered everything', function () {
    $content = makeReadyContent();
    $content->lexemes()->create(['type' => 'word', 'text' => 'run', 'sort_order' => 1]);
    $content->analysisRuns()->create([
        'status' => AiAnalysisRun::STATUS_COMPLETED,
        'coverage_pct' => 100.0,
        'uncovered_words' => [],
    ]);

    $user = User::factory()->create();
    $user->assignRole('user');

    $response = $this->actingAs($user)->getJson("/api/content/{$content->id}/lexemes")->assertOk();

    expect(collect($response->json('lexemes'))->firstWhere('text', 'run')['not_analyzed'])->toBeFalse();
});
