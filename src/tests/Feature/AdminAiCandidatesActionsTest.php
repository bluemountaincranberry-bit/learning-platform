<?php

use App\Filament\Resources\Contents\Pages\ViewContent;
use App\Filament\Resources\Contents\RelationManagers\GrammarCandidatesRelationManager;
use App\Filament\Resources\Contents\RelationManagers\LexemeCandidatesRelationManager;
use App\Modules\Ai\Domain\Models\AiAnalysisRun;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\ContentGrammarCandidate;
use App\Modules\Content\Domain\Models\ContentLexemeCandidate;
use App\Modules\User\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function actingAdminForActions(): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::firstOrCreate(
        ['email' => 'admin-ai-actions@example.com'],
        ['name' => 'Admin User', 'password' => bcrypt('password')]
    );
    if (! $admin->hasRole('admin')) {
        $admin->assignRole('admin');
    }

    test()->actingAs($admin, 'web');

    return $admin;
}

function makeContentWithRun(User $admin): AiAnalysisRun
{
    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'AI candidates actions test',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
        'created_by' => $admin->id,
    ]);

    return $content->analysisRuns()->create(['status' => AiAnalysisRun::STATUS_COMPLETED]);
}

function contentForCandidateRun(AiAnalysisRun $run): Content
{
    return Content::query()->findOrFail($run->content_id);
}

test('accept action sets lexeme candidate status to accepted', function () {
    $admin = actingAdminForActions();
    $run = makeContentWithRun($admin);
    $candidate = $run->lexemeCandidates()->create([
        'text' => 'get up', 'normalized_text' => 'get up', 'type' => 'phrasal_verb', 'status' => ContentLexemeCandidate::STATUS_PENDING,
    ]);

    Livewire::test(LexemeCandidatesRelationManager::class, ['ownerRecord' => contentForCandidateRun($run), 'pageClass' => ViewContent::class])
        ->callTableAction('accept', $candidate);

    expect($candidate->refresh()->status)->toBe(ContentLexemeCandidate::STATUS_ACCEPTED);
});

test('reject action sets lexeme candidate status to rejected', function () {
    $admin = actingAdminForActions();
    $run = makeContentWithRun($admin);
    $candidate = $run->lexemeCandidates()->create([
        'text' => 'get up', 'normalized_text' => 'get up', 'type' => 'phrasal_verb', 'status' => ContentLexemeCandidate::STATUS_PENDING,
    ]);

    Livewire::test(LexemeCandidatesRelationManager::class, ['ownerRecord' => contentForCandidateRun($run), 'pageClass' => ViewContent::class])
        ->callTableAction('reject', $candidate);

    expect($candidate->refresh()->status)->toBe(ContentLexemeCandidate::STATUS_REJECTED);
});

test('ignore match action clears matched lexeme and marks edited', function () {
    $admin = actingAdminForActions();
    $run = makeContentWithRun($admin);
    $lexeme = \App\Modules\Content\Domain\Models\Lexeme::query()->create([
        'slug' => 'en-run', 'language' => 'en', 'lemma' => 'run', 'normalized_lemma' => 'run', 'status' => \App\Modules\Content\Domain\Models\Lexeme::STATUS_PUBLISHED,
    ]);
    $candidate = $run->lexemeCandidates()->create([
        'text' => 'run', 'normalized_text' => 'run', 'type' => 'word', 'status' => ContentLexemeCandidate::STATUS_PENDING,
        'matched_lexeme_id' => $lexeme->id, 'match_score' => 1.0,
    ]);

    Livewire::test(LexemeCandidatesRelationManager::class, ['ownerRecord' => contentForCandidateRun($run), 'pageClass' => ViewContent::class])
        ->callTableAction('ignoreMatch', $candidate);

    $candidate->refresh();
    expect($candidate->matched_lexeme_id)->toBeNull()
        ->and($candidate->status)->toBe(ContentLexemeCandidate::STATUS_EDITED);
});

test('editing the translation column persists and marks status edited', function () {
    $admin = actingAdminForActions();
    $run = makeContentWithRun($admin);
    $candidate = $run->lexemeCandidates()->create([
        'text' => 'get up', 'normalized_text' => 'get up', 'type' => 'phrasal_verb', 'status' => ContentLexemeCandidate::STATUS_ACCEPTED,
    ]);

    Livewire::test(LexemeCandidatesRelationManager::class, ['ownerRecord' => contentForCandidateRun($run), 'pageClass' => ViewContent::class])
        ->call('updateTableColumnState', 'translation', (string) $candidate->id, 'вставать');

    $candidate->refresh();
    expect($candidate->translation)->toBe('вставать')
        ->and($candidate->status)->toBe(ContentLexemeCandidate::STATUS_EDITED);
});

test('bulk accept selected sets status accepted for chosen records only', function () {
    $admin = actingAdminForActions();
    $run = makeContentWithRun($admin);
    $a = $run->lexemeCandidates()->create(['text' => 'a', 'normalized_text' => 'a', 'type' => 'word', 'status' => ContentLexemeCandidate::STATUS_PENDING]);
    $b = $run->lexemeCandidates()->create(['text' => 'b', 'normalized_text' => 'b', 'type' => 'word', 'status' => ContentLexemeCandidate::STATUS_PENDING]);

    Livewire::test(LexemeCandidatesRelationManager::class, ['ownerRecord' => contentForCandidateRun($run), 'pageClass' => ViewContent::class])
        ->callTableBulkAction('acceptSelected', [$a]);

    expect($a->refresh()->status)->toBe(ContentLexemeCandidate::STATUS_ACCEPTED)
        ->and($b->refresh()->status)->toBe(ContentLexemeCandidate::STATUS_PENDING);
});

test('accept above threshold header action only accepts candidates from the latest run', function () {
    $admin = actingAdminForActions();
    $content = Content::query()->create([
        'type' => 'youtube', 'title' => 'Threshold test', 'language' => 'en', 'origin' => 'curated', 'status' => 'ready', 'created_by' => $admin->id,
    ]);
    $oldRun = $content->analysisRuns()->create(['status' => AiAnalysisRun::STATUS_COMPLETED]);
    $staleCandidate = $oldRun->lexemeCandidates()->create([
        'text' => 'stale', 'normalized_text' => 'stale', 'type' => 'word', 'status' => ContentLexemeCandidate::STATUS_PENDING, 'confidence' => 0.99,
    ]);
    $newRun = $content->analysisRuns()->create(['status' => AiAnalysisRun::STATUS_COMPLETED]);
    $highConfidence = $newRun->lexemeCandidates()->create([
        'text' => 'high', 'normalized_text' => 'high', 'type' => 'word', 'status' => ContentLexemeCandidate::STATUS_PENDING, 'confidence' => 0.95,
    ]);
    $lowConfidence = $newRun->lexemeCandidates()->create([
        'text' => 'low', 'normalized_text' => 'low', 'type' => 'word', 'status' => ContentLexemeCandidate::STATUS_PENDING, 'confidence' => 0.4,
    ]);

    Livewire::test(LexemeCandidatesRelationManager::class, ['ownerRecord' => $content->fresh(), 'pageClass' => ViewContent::class])
        ->callTableAction('acceptAboveThreshold', data: ['threshold' => 0.8]);

    expect($highConfidence->refresh()->status)->toBe(ContentLexemeCandidate::STATUS_ACCEPTED)
        ->and($lowConfidence->refresh()->status)->toBe(ContentLexemeCandidate::STATUS_PENDING)
        ->and($staleCandidate->refresh()->status)->toBe(ContentLexemeCandidate::STATUS_PENDING);
});

test('grammar candidate accept/reject/ignore match actions work the same way', function () {
    $admin = actingAdminForActions();
    $run = makeContentWithRun($admin);
    $rule = \App\Modules\Content\Domain\Models\GrammarRule::query()->create([
        'topic_id' => \App\Modules\Content\Domain\Models\GrammarTopic::query()->create(['slug' => 'topic-x', 'language' => 'en', 'name' => 'Topic X', 'status' => 'active'])->id,
        'slug' => 'rule-x', 'language' => 'en', 'title' => 'Rule X', 'status' => \App\Modules\Content\Domain\Models\GrammarRule::STATUS_PUBLISHED,
    ]);
    $candidate = $run->grammarCandidates()->create([
        'title' => 'Present Perfect', 'status' => ContentGrammarCandidate::STATUS_PENDING,
        'matched_grammar_rule_id' => $rule->id, 'match_score' => 1.0,
    ]);

    $component = Livewire::test(GrammarCandidatesRelationManager::class, ['ownerRecord' => contentForCandidateRun($run), 'pageClass' => ViewContent::class]);

    $component->callTableAction('ignoreMatch', $candidate);
    $candidate->refresh();
    expect($candidate->matched_grammar_rule_id)->toBeNull()
        ->and($candidate->status)->toBe(ContentGrammarCandidate::STATUS_EDITED);

    $component->callTableAction('accept', $candidate);
    expect($candidate->refresh()->status)->toBe(ContentGrammarCandidate::STATUS_ACCEPTED);
});
