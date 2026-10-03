<?php

use App\Contracts\Ai\EmbeddingsClientInterface;
use App\Modules\Ai\Application\CandidateMatchingService;
use App\Modules\Ai\Application\LessonCandidateMatchingService;
use App\Modules\Ai\Domain\Models\GrammarRuleEmbedding;
use App\Modules\Content\Application\GrammarProgressService;
use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Domain\Models\GrammarTopic;
use App\Modules\Content\Domain\Models\Lexeme;
use App\Modules\Learning\Domain\Models\Lesson;
use App\Modules\Learning\Domain\Models\LessonAnalysisRun;
use App\Modules\Learning\Domain\Models\UserGrammarRule;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeLessonRunForMatching(): LessonAnalysisRun
{
    $user = User::factory()->create();
    $lesson = Lesson::query()->create(['user_id' => $user->id, 'status' => Lesson::STATUS_ACTIVE, 'source_text' => 'x']);

    return $lesson->analysisRuns()->create(['status' => LessonAnalysisRun::STATUS_PENDING]);
}

function lessonMatcher(EmbeddingsClientInterface $embeddings): LessonCandidateMatchingService
{
    return new LessonCandidateMatchingService(
        new CandidateMatchingService($embeddings),
        app(GrammarProgressService::class),
        app(\App\Modules\Learning\Application\Contracts\LessonAnalysisStoreInterface::class)
    );
}

test('a matched lexeme candidate is marked matched but never auto-linked into user progress', function () {
    $run = makeLessonRunForMatching();
    $lexeme = Lexeme::query()->create([
        'slug' => 'en-get-up', 'language' => 'en', 'lemma' => 'get up', 'normalized_lemma' => 'get up', 'status' => Lexeme::STATUS_PUBLISHED,
    ]);
    $candidate = $run->lexemeCandidates()->create(['text' => 'get up', 'normalized_text' => 'get up', 'type' => 'word', 'status' => 'pending']);

    $embeddings = Mockery::mock(EmbeddingsClientInterface::class);
    $embeddings->shouldNotReceive('embed');
    $embeddings->shouldNotReceive('embedBatch');

    lessonMatcher($embeddings)->matchRun($run->id);

    $candidate->refresh();
    expect($candidate->matched_lexeme_id)->toBe($lexeme->id)
        ->and($candidate->status)->toBe('matched');

    // No user_lexeme_progress row was created — see LessonCandidateMatchingService's
    // docblock for why lexeme matches stay reference-only in this round.
    expect(\App\Modules\Learning\Domain\Models\UserLexemeProgress::query()->count())->toBe(0);
});

test('an unmatched lexeme candidate is marked new', function () {
    $run = makeLessonRunForMatching();
    $candidate = $run->lexemeCandidates()->create(['text' => 'zzzznotaword', 'normalized_text' => 'zzzznotaword', 'type' => 'word', 'status' => 'pending']);

    $embeddings = Mockery::mock(EmbeddingsClientInterface::class);
    $embeddings->shouldNotReceive('embed');

    lessonMatcher($embeddings)->matchRun($run->id);

    $candidate->refresh();
    expect($candidate->matched_lexeme_id)->toBeNull()
        ->and($candidate->status)->toBe('new');
});

test('a matched grammar candidate is auto-linked into the lesson owner\'s UserGrammarRule', function () {
    config(['ai.analysis.match_threshold' => 0.8]);
    $run = makeLessonRunForMatching();
    $topic = GrammarTopic::query()->create(['slug' => 'perfect', 'language' => 'en', 'name' => 'Perfect', 'status' => 'active']);
    $rule = GrammarRule::query()->create([
        'topic_id' => $topic->id, 'slug' => 'present-perfect', 'language' => 'en', 'title' => 'Present Perfect', 'status' => GrammarRule::STATUS_PUBLISHED,
    ]);
    GrammarRuleEmbedding::query()->create(['grammar_rule_id' => $rule->id, 'embedding' => [1.0, 0.0], 'model_version' => 'text-embedding-3-small']);
    $candidate = $run->grammarCandidates()->create(['title' => 'Present Perfect', 'summary' => 'unfinished past action', 'status' => 'pending']);

    $embeddings = Mockery::mock(EmbeddingsClientInterface::class);
    $embeddings->shouldReceive('embed')->once()->andReturn([1.0, 0.0]);

    lessonMatcher($embeddings)->matchRun($run->id);

    $candidate->refresh();
    expect($candidate->matched_grammar_rule_id)->toBe($rule->id)
        ->and($candidate->status)->toBe('linked');

    expect(UserGrammarRule::query()->where('user_id', $run->lesson->user_id)->where('grammar_rule_id', $rule->id)->exists())->toBeTrue();
});

test('an unmatched grammar candidate is marked new and links nothing', function () {
    $run = makeLessonRunForMatching();
    $candidate = $run->grammarCandidates()->create(['title' => 'Some obscure construction', 'status' => 'pending']);

    $embeddings = Mockery::mock(EmbeddingsClientInterface::class);
    $embeddings->shouldNotReceive('embed');

    lessonMatcher($embeddings)->matchRun($run->id);

    $candidate->refresh();
    expect($candidate->matched_grammar_rule_id)->toBeNull()
        ->and($candidate->status)->toBe('new')
        ->and(UserGrammarRule::query()->count())->toBe(0);
});
