<?php

use App\Modules\Ai\Domain\Models\AiAnalysisRun;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\ContentGrammarCandidate;
use App\Modules\Content\Domain\Models\ContentLexemeCandidate;
use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Domain\Models\Lexeme;
use App\Modules\Ai\Application\AiCandidateAutoApplyService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeAutoApplyRun(): AiAnalysisRun
{
    $content = Content::query()->create([
        'type' => 'youtube', 'title' => 'Auto-apply test', 'language' => 'en', 'origin' => 'user-submitted', 'status' => 'ready',
        'source_text' => 'run maybe unsure jump twice Present Simple',
    ]);

    return $content->analysisRuns()->create([
        'status' => AiAnalysisRun::STATUS_COMPLETED,
        'config' => ['target_level' => 'A1'],
    ]);
}

test('autoApply applies a high-confidence candidate', function () {
    $run = makeAutoApplyRun();
    $candidate = $run->lexemeCandidates()->create([
        'text' => 'run', 'normalized_text' => 'run', 'type' => 'word', 'confidence' => 0.8,
        'translation' => 'бегать', 'level' => 'A1', 'status' => ContentLexemeCandidate::STATUS_PENDING,
    ]);

    $result = app(AiCandidateAutoApplyService::class)->autoApply($run);

    expect($result['applied'])->toBe(['lexemes' => 1, 'grammar' => 0])
        ->and($candidate->fresh()->status)->toBe(ContentLexemeCandidate::STATUS_APPLIED);
    expect(Lexeme::query()->where('normalized_lemma', 'run')->where('language', 'en')->exists())->toBeTrue();
});

test('autoApply rejects a low-confidence candidate', function () {
    $run = makeAutoApplyRun();
    $candidate = $run->lexemeCandidates()->create([
        'text' => 'maybe', 'normalized_text' => 'maybe', 'type' => 'word', 'confidence' => 0.2,
        'translation' => 'может быть', 'level' => 'A1', 'status' => ContentLexemeCandidate::STATUS_PENDING,
    ]);

    app(AiCandidateAutoApplyService::class)->autoApply($run);

    expect($candidate->fresh()->status)->toBe(ContentLexemeCandidate::STATUS_REJECTED);
    expect(Lexeme::query()->where('normalized_lemma', 'maybe')->exists())->toBeFalse();
});

test('autoApply rejects a candidate without translation, level or confidence', function () {
    $run = makeAutoApplyRun();
    $candidate = $run->lexemeCandidates()->create([
        'text' => 'unsure', 'normalized_text' => 'unsure', 'type' => 'word', 'confidence' => null,
        'status' => ContentLexemeCandidate::STATUS_PENDING,
    ]);

    app(AiCandidateAutoApplyService::class)->autoApply($run);

    expect($candidate->fresh()->status)->toBe(ContentLexemeCandidate::STATUS_REJECTED);
});

test('autoApply rejects a non-russian translation when the run targets russian', function () {
    $run = makeAutoApplyRun();
    $candidate = $run->lexemeCandidates()->create([
        'text' => 'run', 'normalized_text' => 'run', 'type' => 'word', 'confidence' => 0.9,
        'translation' => 'correr', 'level' => 'A1', 'status' => ContentLexemeCandidate::STATUS_PENDING,
    ]);

    app(AiCandidateAutoApplyService::class)->autoApply($run);

    expect($candidate->fresh()->status)->toBe(ContentLexemeCandidate::STATUS_REJECTED);
});

test('autoApply applies lexeme and grammar candidates independently in the same run', function () {
    $run = makeAutoApplyRun();
    $lexeme = $run->lexemeCandidates()->create([
        'text' => 'jump', 'normalized_text' => 'jump', 'type' => 'word', 'confidence' => 0.9,
        'translation' => 'прыгать', 'level' => 'A1', 'status' => ContentLexemeCandidate::STATUS_PENDING,
    ]);
    $grammar = $run->grammarCandidates()->create([
        'title' => 'Present Simple', 'confidence' => 0.1, 'status' => ContentGrammarCandidate::STATUS_PENDING,
    ]);

    $result = app(AiCandidateAutoApplyService::class)->autoApply($run);

    expect($result['applied'])->toBe(['lexemes' => 1, 'grammar' => 1])
        ->and($lexeme->fresh()->status)->toBe(ContentLexemeCandidate::STATUS_APPLIED)
        ->and($grammar->fresh()->status)->toBe(ContentGrammarCandidate::STATUS_APPLIED);
    expect(GrammarRule::query()->where('title', 'Present Simple')->exists())->toBeTrue();
});

test('autoApply is idempotent when run twice on the same run', function () {
    $run = makeAutoApplyRun();
    $run->lexemeCandidates()->create([
        'text' => 'twice', 'normalized_text' => 'twice', 'type' => 'word', 'confidence' => 0.9,
        'translation' => 'дважды', 'level' => 'A1', 'status' => ContentLexemeCandidate::STATUS_PENDING,
    ]);

    $service = app(AiCandidateAutoApplyService::class);
    $first = $service->autoApply($run);
    $second = $service->autoApply($run);

    expect($first['applied'])->toBe(['lexemes' => 1, 'grammar' => 0])
        ->and($second['applied'])->toBe(['lexemes' => 0, 'grammar' => 0])
        ->and(Lexeme::query()->where('normalized_lemma', 'twice')->count())->toBe(1);
});
