<?php

use App\Modules\Ai\Interfaces\Jobs\RunAiContentAnalysisJob;
use App\Modules\Ai\Domain\Models\AiAnalysisRun;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\ContentLexemeCandidate;
use App\Modules\Content\Domain\Models\Lexeme;
use App\Contracts\Ai\AiJsonClient;
use App\Contracts\Ai\EmbeddingsClientInterface;
use App\Modules\Content\Domain\Models\GrammarRule;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeContentWithTranscript(string $title, string $sourceText): Content
{
    return Content::query()->create([
        'type' => 'youtube',
        'title' => $title,
        'language' => 'en',
        'origin' => 'user-submitted',
        'status' => 'ready',
        'source_text' => $sourceText,
    ]);
}

test('the live job applies valid candidates and rejects low-confidence candidates', function () {
    $content = makeContentWithTranscript('Video', 'I like to run and jump every day.');
    $run = $content->analysisRuns()->create(['status' => AiAnalysisRun::STATUS_PENDING]);

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andReturn([
        'lexemes' => [
            ['text' => 'run', 'type' => 'word', 'translation' => 'бегать', 'level' => 'A1', 'confidence' => 0.92],
            ['text' => 'jump', 'type' => 'word', 'translation' => 'прыгать', 'level' => 'A1', 'confidence' => 0.1],
        ],
        'grammar' => [],
    ]);
    $this->app->instance(AiJsonClient::class, $client);

    app()->call([new RunAiContentAnalysisJob($run->id), 'handle']);

    $run->refresh();
    expect($run->status)->toBe(AiAnalysisRun::STATUS_COMPLETED);

    $byText = $run->lexemeCandidates()->get()->keyBy('text');
    expect($byText['run']['status'])->toBe(ContentLexemeCandidate::STATUS_APPLIED)
        ->and($byText['jump']['status'])->toBe(ContentLexemeCandidate::STATUS_REJECTED);

    // Applied through the real, unmodified AiCandidateApplyService — a
    // proper typed, translated word in the canonical catalog, not just a
    // flipped candidate status.
    $lexeme = Lexeme::query()->where('normalized_lemma', 'run')->where('language', 'en')->first();
    expect($lexeme)->not->toBeNull();
    expect($lexeme->translations()->where('translation', 'бегать')->exists())->toBeTrue();

    expect(Lexeme::query()->where('normalized_lemma', 'jump')->exists())->toBeFalse();
});

test('regression: auto-applying the same word from two different contents never creates a duplicate canonical Lexeme', function () {
    $contentA = makeContentWithTranscript('Video A', 'I run every morning.');
    $runA = $contentA->analysisRuns()->create(['status' => AiAnalysisRun::STATUS_PENDING]);
    $clientA = Mockery::mock(AiJsonClient::class);
    $clientA->shouldReceive('completeJson')->once()->andReturn([
        'lexemes' => [['text' => 'run', 'type' => 'word', 'translation' => 'бегать', 'level' => 'A1', 'confidence' => 0.9]],
        'grammar' => [],
    ]);
    $this->app->instance(AiJsonClient::class, $clientA);
    app()->call([new RunAiContentAnalysisJob($runA->id), 'handle']);

    // A second, independent content proposing the exact same word. No
    // embeddings client is configured/mocked here — CandidateMatchingService's
    // exact normalized-lemma match (checked before any embedding call) is
    // what must catch this, deterministically, with no human review in
    // between the two runs to have caught it manually.
    $contentB = makeContentWithTranscript('Video B', 'She likes to run too.');
    $runB = $contentB->analysisRuns()->create(['status' => AiAnalysisRun::STATUS_PENDING]);
    $clientB = Mockery::mock(AiJsonClient::class);
    $clientB->shouldReceive('completeJson')->once()->andReturn([
        'lexemes' => [['text' => 'run', 'type' => 'word', 'translation' => 'бежать', 'level' => 'A1', 'confidence' => 0.9]],
        'grammar' => [],
    ]);
    $this->app->instance(AiJsonClient::class, $clientB);
    app()->call([new RunAiContentAnalysisJob($runB->id), 'handle']);

    $runA->refresh();
    $runB->refresh();
    expect($runA->status)->toBe(AiAnalysisRun::STATUS_COMPLETED)
        ->and($runB->status)->toBe(AiAnalysisRun::STATUS_COMPLETED);

    expect(Lexeme::query()->where('language', 'en')->where('normalized_lemma', 'run')->count())->toBe(1);

    // Both content pieces still get their own ContentLexeme occurrence
    // pointing at that one shared canonical lexeme.
    $lexeme = Lexeme::query()->where('language', 'en')->where('normalized_lemma', 'run')->first();
    expect($contentA->lexemes()->where('lexeme_id', $lexeme->id)->exists())->toBeTrue();
    expect($contentB->lexemes()->where('lexeme_id', $lexeme->id)->exists())->toBeTrue();
});

test('regression VIK-16: re-running analysis that proposes an existing rule title with a new summary links the existing rule instead of creating a duplicate', function () {
    // The incident: "Passive Voice" was proposed again with a freshly
    // generated summary; its title+summary embedding scored 0.758 against the
    // existing "Passive Voice" rule (< 0.85), so Apply created a second rule.
    // Disagreeing vectors reproduce "embeddings exist but miss the match".
    $embeddings = Mockery::mock(EmbeddingsClientInterface::class);
    $embeddings->shouldReceive('embed')->andReturn([0.0, 1.0]);
    $embeddings->shouldReceive('embedBatch')->andReturnUsing(fn (array $texts) => array_fill(0, count($texts), [1.0, 0.0]));
    $this->app->instance(EmbeddingsClientInterface::class, $embeddings);

    $content = makeContentWithTranscript('Video', 'If it rains, we will stay home.');

    foreach ([
        ['First Conditional', 'Real possibility in the future.'],
        [' first  conditional ', 'Used for likely results of a condition.'],
    ] as [$title, $summary]) {
        $run = $content->analysisRuns()->create(['status' => AiAnalysisRun::STATUS_PENDING]);
        $client = Mockery::mock(AiJsonClient::class);
        $client->shouldReceive('completeJson')->once()->andReturn([
            'lexemes' => [],
            'grammar' => [['title' => $title, 'summary' => $summary, 'example' => 'If it rains, we will stay home.', 'confidence' => 0.9]],
        ]);
        $this->app->instance(AiJsonClient::class, $client);
        app()->call([new RunAiContentAnalysisJob($run->id), 'handle']);
        expect($run->refresh()->status)->toBe(AiAnalysisRun::STATUS_COMPLETED);
    }

    $rules = GrammarRule::query()->get();
    expect($rules)->toHaveCount(1)
        ->and($content->grammarRules()->pluck('grammar_rules.id')->all())->toBe([$rules->first()->id]);
});
