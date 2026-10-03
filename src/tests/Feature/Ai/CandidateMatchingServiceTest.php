<?php

use App\Modules\Ai\Domain\Models\AiAnalysisRun;
use App\Modules\Ai\Domain\Models\CanonicalLexemeEmbedding;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Ai\Domain\Models\GrammarRuleEmbedding;
use App\Modules\Content\Domain\Models\GrammarTopic;
use App\Modules\Content\Domain\Models\Lexeme;
use App\Modules\Ai\Application\CandidateMatchingService;
use App\Contracts\Ai\EmbeddingsClientInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeRunForMatching(): AiAnalysisRun
{
    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'Matching test',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
        'source_text' => 'text',
    ]);

    return $content->analysisRuns()->create(['status' => AiAnalysisRun::STATUS_PENDING]);
}

test('exact normalized match sets score 1.0 and does not call embeddings', function () {
    $run = makeRunForMatching();
    $lexeme = Lexeme::query()->create([
        'slug' => 'en-get-up',
        'language' => 'en',
        'lemma' => 'get up',
        'normalized_lemma' => 'get up',
        'status' => Lexeme::STATUS_PUBLISHED,
    ]);
    $candidate = $run->lexemeCandidates()->create([
        'text' => 'get up',
        'normalized_text' => 'get up',
        'type' => 'phrasal_verb',
        'status' => 'pending',
    ]);

    $embeddings = Mockery::mock(EmbeddingsClientInterface::class);
    $embeddings->shouldNotReceive('embedBatch');

    (new CandidateMatchingService($embeddings))->matchRun($run);

    $candidate->refresh();
    expect($candidate->matched_lexeme_id)->toBe($lexeme->id)
        ->and($candidate->match_score)->toBe(1.0);
});

test('embedding fallback matches the closest canonical lexeme above threshold', function () {
    config(['ai.analysis.match_threshold' => 0.8]);
    $run = makeRunForMatching();
    $lexeme = Lexeme::query()->create([
        'slug' => 'en-run',
        'language' => 'en',
        'lemma' => 'run',
        'normalized_lemma' => 'run',
        'status' => Lexeme::STATUS_PUBLISHED,
    ]);
    CanonicalLexemeEmbedding::query()->create([
        'lexeme_id' => $lexeme->id,
        'embedding' => [1.0, 0.0],
        'model_version' => 'text-embedding-3-small',
    ]);
    $candidate = $run->lexemeCandidates()->create([
        'text' => 'running',
        'normalized_text' => 'running',
        'type' => 'word',
        'status' => 'pending',
    ]);

    $embeddings = Mockery::mock(EmbeddingsClientInterface::class);
    $embeddings->shouldReceive('embedBatch')->once()->with(['running'])->andReturn([[0.99, 0.01]]);

    (new CandidateMatchingService($embeddings))->matchRun($run);

    $candidate->refresh();
    expect($candidate->matched_lexeme_id)->toBe($lexeme->id)
        ->and($candidate->match_score)->toBeGreaterThan(0.9);
});

test('embedding fallback below threshold leaves matched_lexeme_id null but stores score', function () {
    config(['ai.analysis.match_threshold' => 0.95]);
    $run = makeRunForMatching();
    $lexeme = Lexeme::query()->create([
        'slug' => 'en-run',
        'language' => 'en',
        'lemma' => 'run',
        'normalized_lemma' => 'run',
        'status' => Lexeme::STATUS_PUBLISHED,
    ]);
    CanonicalLexemeEmbedding::query()->create([
        'lexeme_id' => $lexeme->id,
        'embedding' => [1.0, 0.0],
        'model_version' => 'text-embedding-3-small',
    ]);
    $candidate = $run->lexemeCandidates()->create([
        'text' => 'unrelated',
        'normalized_text' => 'unrelated',
        'type' => 'word',
        'status' => 'pending',
    ]);

    $embeddings = Mockery::mock(EmbeddingsClientInterface::class);
    $embeddings->shouldReceive('embedBatch')->once()->andReturn([[0.0, 1.0]]);

    (new CandidateMatchingService($embeddings))->matchRun($run);

    $candidate->refresh();
    expect($candidate->matched_lexeme_id)->toBeNull()
        ->and($candidate->match_score)->toBe(0.0);
});

test('no canonical embeddings present is a no-op, not an error', function () {
    $run = makeRunForMatching();
    $candidate = $run->lexemeCandidates()->create([
        'text' => 'anything',
        'normalized_text' => 'anything',
        'type' => 'word',
        'status' => 'pending',
    ]);

    $embeddings = Mockery::mock(EmbeddingsClientInterface::class);
    $embeddings->shouldNotReceive('embedBatch');

    (new CandidateMatchingService($embeddings))->matchRun($run);

    $candidate->refresh();
    expect($candidate->matched_lexeme_id)->toBeNull()
        ->and($candidate->match_score)->toBeNull();
});

test('grammar candidate matches via title and summary embedding', function () {
    config(['ai.analysis.match_threshold' => 0.8]);
    $run = makeRunForMatching();
    $topic = GrammarTopic::query()->create([
        'slug' => 'perfect-tenses',
        'language' => 'en',
        'name' => 'Perfect tenses',
        'status' => 'active',
    ]);
    $rule = GrammarRule::query()->create([
        'topic_id' => $topic->id,
        'slug' => 'present-perfect-continuous',
        'language' => 'en',
        'title' => 'Present Perfect Continuous',
        'status' => GrammarRule::STATUS_PUBLISHED,
        'summary' => 'Unfinished past action',
    ]);
    GrammarRuleEmbedding::query()->create([
        'grammar_rule_id' => $rule->id,
        'embedding' => [1.0, 0.0],
        'model_version' => 'text-embedding-3-small',
    ]);
    $candidate = $run->grammarCandidates()->create([
        'title' => 'Present Perfect Continuous',
        'summary' => 'unfinished past action',
        'status' => 'pending',
    ]);

    $embeddings = Mockery::mock(EmbeddingsClientInterface::class);
    $embeddings->shouldReceive('embedBatch')->once()->andReturn([[1.0, 0.0]]);

    (new CandidateMatchingService($embeddings))->matchRun($run);

    $candidate->refresh();
    expect($candidate->matched_grammar_rule_id)->toBe($rule->id)
        ->and($candidate->match_score)->toBe(1.0);
});

// --- Task 10.1: match by lemma, not the raw inflected occurrence text ---

test('exact match resolves by lemma, not the raw inflected occurrence text', function () {
    $run = makeRunForMatching();
    $lexeme = Lexeme::query()->create([
        'slug' => 'en-run', 'language' => 'en', 'lemma' => 'run', 'normalized_lemma' => 'run', 'status' => Lexeme::STATUS_PUBLISHED,
    ]);
    $candidate = $run->lexemeCandidates()->create([
        'text' => 'ran', 'normalized_text' => 'ran', 'lemma' => 'run', 'normalized_lemma' => 'run', 'type' => 'word', 'status' => 'pending',
    ]);

    $embeddings = Mockery::mock(EmbeddingsClientInterface::class);
    $embeddings->shouldNotReceive('embedBatch');

    (new CandidateMatchingService($embeddings))->matchRun($run);

    $candidate->refresh();
    expect($candidate->matched_lexeme_id)->toBe($lexeme->id)
        ->and($candidate->match_score)->toBe(1.0);
});

test('embedding fallback embeds the candidate lemma, not the raw occurrence text', function () {
    config(['ai.analysis.match_threshold' => 0.8]);
    $run = makeRunForMatching();
    $lexeme = Lexeme::query()->create([
        'slug' => 'en-jog', 'language' => 'en', 'lemma' => 'jog', 'normalized_lemma' => 'jog', 'status' => Lexeme::STATUS_PUBLISHED,
    ]);
    CanonicalLexemeEmbedding::query()->create(['lexeme_id' => $lexeme->id, 'embedding' => [1.0, 0.0], 'model_version' => 'text-embedding-3-small']);

    $candidate = $run->lexemeCandidates()->create([
        'text' => 'sprinted', 'normalized_text' => 'sprinted', 'lemma' => 'sprint', 'normalized_lemma' => 'sprint', 'type' => 'word', 'status' => 'pending',
    ]);

    $embeddings = Mockery::mock(EmbeddingsClientInterface::class);
    $embeddings->shouldReceive('embedBatch')->once()->with(['sprint'])->andReturn([[0.99, 0.01]]);

    (new CandidateMatchingService($embeddings))->matchRun($run);

    expect($candidate->refresh()->matched_lexeme_id)->toBe($lexeme->id);
});

test('a candidate created before the lemma columns existed still matches by normalized_text', function () {
    $run = makeRunForMatching();
    $lexeme = Lexeme::query()->create([
        'slug' => 'en-cat', 'language' => 'en', 'lemma' => 'cat', 'normalized_lemma' => 'cat', 'status' => Lexeme::STATUS_PUBLISHED,
    ]);
    $candidate = $run->lexemeCandidates()->create([
        'text' => 'cat', 'normalized_text' => 'cat', 'type' => 'word', 'status' => 'pending',
    ]);

    $embeddings = Mockery::mock(EmbeddingsClientInterface::class);
    $embeddings->shouldNotReceive('embedBatch');

    (new CandidateMatchingService($embeddings))->matchRun($run);

    expect($candidate->refresh()->matched_lexeme_id)->toBe($lexeme->id);
});

// --- Task 4.9: cheap fan-out (one batched embeddings call, not N sequential ones) ---

test('many lexeme candidates needing the embedding fallback trigger exactly one embedBatch call, not one per candidate', function () {
    config(['ai.analysis.match_threshold' => 0.8]);
    $run = makeRunForMatching();
    $lexeme = Lexeme::query()->create([
        'slug' => 'en-run2', 'language' => 'en', 'lemma' => 'run', 'normalized_lemma' => 'run', 'status' => Lexeme::STATUS_PUBLISHED,
    ]);
    CanonicalLexemeEmbedding::query()->create(['lexeme_id' => $lexeme->id, 'embedding' => [1.0, 0.0], 'model_version' => 'text-embedding-3-small']);

    $texts = ['running', 'ran', 'runner', 'runs', 'jogging'];
    foreach ($texts as $text) {
        $run->lexemeCandidates()->create(['text' => $text, 'normalized_text' => $text, 'type' => 'word', 'status' => 'pending']);
    }

    $embeddings = Mockery::mock(EmbeddingsClientInterface::class);
    $embeddings->shouldReceive('embedBatch')
        ->once() // exactly once for all 5 candidates together, not 5 times
        ->with($texts)
        ->andReturn(array_fill(0, count($texts), [0.9, 0.1]));

    (new CandidateMatchingService($embeddings))->matchRun($run);

    expect($run->lexemeCandidates()->whereNotNull('matched_lexeme_id')->count())->toBe(count($texts));
});

test('a mix of exact and embedding-fallback candidates only batches the ones that actually need embedding', function () {
    config(['ai.analysis.match_threshold' => 0.8]);
    $run = makeRunForMatching();
    $exactLexeme = Lexeme::query()->create([
        'slug' => 'en-cat', 'language' => 'en', 'lemma' => 'cat', 'normalized_lemma' => 'cat', 'status' => Lexeme::STATUS_PUBLISHED,
    ]);
    $fallbackLexeme = Lexeme::query()->create([
        'slug' => 'en-dog', 'language' => 'en', 'lemma' => 'dog', 'normalized_lemma' => 'dog', 'status' => Lexeme::STATUS_PUBLISHED,
    ]);
    CanonicalLexemeEmbedding::query()->create(['lexeme_id' => $fallbackLexeme->id, 'embedding' => [1.0, 0.0], 'model_version' => 'text-embedding-3-small']);

    $run->lexemeCandidates()->create(['text' => 'cat', 'normalized_text' => 'cat', 'type' => 'word', 'status' => 'pending']);
    $run->lexemeCandidates()->create(['text' => 'puppy', 'normalized_text' => 'puppy', 'type' => 'word', 'status' => 'pending']);

    $embeddings = Mockery::mock(EmbeddingsClientInterface::class);
    $embeddings->shouldReceive('embedBatch')->once()->with(['puppy'])->andReturn([[0.9, 0.1]]);

    (new CandidateMatchingService($embeddings))->matchRun($run);

    expect($run->lexemeCandidates()->where('text', 'cat')->value('match_score'))->toBe(1.0)
        ->and($run->lexemeCandidates()->where('text', 'puppy')->value('matched_lexeme_id'))->toBe($fallbackLexeme->id);
});
