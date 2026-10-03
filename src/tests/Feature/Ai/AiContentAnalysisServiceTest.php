<?php

use App\Exceptions\AiClientException;
use App\Modules\Ai\Domain\Models\AgentTraceSpan;
use App\Modules\Ai\Domain\Models\AiAnalysisRun;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Domain\Models\GrammarTopic;
use App\Modules\Ai\Domain\Models\PromptTemplate;
use App\Modules\Ai\Application\Agent\Tracing\TracedLlmCall;
use App\Modules\Ai\Application\AiContentAnalysisService;
use App\Modules\Ai\Application\Data\TokenUsage;
use App\Contracts\Ai\AiJsonClient;
use App\Contracts\Ai\AiUsageReportingClient;
use App\Contracts\Ai\PromptRegistryInterface;
use App\Modules\Content\Application\ContentTokenizer;
use Illuminate\Support\Facades\Config;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function makeAnalysisRun(string $sourceText = 'I have been waiting for you to get up.'): AiAnalysisRun
{
    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'Test video',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
        'source_text' => $sourceText,
    ]);

    return $content->analysisRuns()->create(['status' => AiAnalysisRun::STATUS_PENDING]);
}

test('analyze persists lexeme and grammar candidates from a valid AI response', function () {
    $run = makeAnalysisRun();

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')
        ->once()
        ->with(
            Mockery::on(fn ($s) => str_contains($s, 'language-learning content analyst') && str_contains($s, 'ru')),
            Mockery::on(fn ($u) => str_contains($u, 'get up')),
            Mockery::type('array'), Mockery::any()
        )
        ->andReturn([
            'lexemes' => [
                ['text' => 'get up', 'type' => 'phrasal_verb', 'translation' => 'вставать', 'example' => 'get up to you', 'confidence' => 0.9],
                ['text' => '', 'type' => 'word'], // invalid, must be skipped
            ],
            'grammar' => [
                ['title' => 'Present Perfect Continuous', 'summary' => 'unfinished past action', 'example' => 'I have been waiting', 'confidence' => 0.8],
            ],
        ]);

    (new AiContentAnalysisService($client, new ContentTokenizer, app(TracedLlmCall::class), app(PromptRegistryInterface::class)))->analyze($run);

    expect($run->lexemeCandidates()->count())->toBe(1)
        ->and($run->grammarCandidates()->count())->toBe(1);

    $lexeme = $run->lexemeCandidates()->first();
    expect($lexeme->text)->toBe('get up')
        ->and($lexeme->normalized_text)->toBe('get up')
        ->and($lexeme->type)->toBe('phrasal_verb')
        ->and($lexeme->translation)->toBe('вставать')
        ->and($lexeme->status)->toBe('pending')
        ->and($lexeme->confidence)->toBe(0.9);

    $grammar = $run->grammarCandidates()->first();
    expect($grammar->title)->toBe('Present Perfect Continuous')
        ->and($grammar->status)->toBe('pending');
});

test('analyze falls back to word type when AI returns an unknown type', function () {
    $run = makeAnalysisRun();

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andReturn([
        'lexemes' => [
            ['text' => 'run', 'type' => 'not-a-real-type'],
        ],
        'grammar' => [],
    ]);

    (new AiContentAnalysisService($client, new ContentTokenizer, app(TracedLlmCall::class), app(PromptRegistryInterface::class)))->analyze($run);

    expect($run->lexemeCandidates()->first()->type)->toBe('word');
});

test('analyze throws when content has no transcript', function () {
    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'Empty',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
    ]);
    $run = $content->analysisRuns()->create(['status' => AiAnalysisRun::STATUS_PENDING]);

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldNotReceive('completeJson');

    (new AiContentAnalysisService($client, new ContentTokenizer, app(TracedLlmCall::class), app(PromptRegistryInterface::class)))->analyze($run);
})->throws(AiClientException::class, 'no transcript');

test('analyze throws when AI response has no usable candidates', function () {
    $run = makeAnalysisRun();

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andReturn(['lexemes' => [], 'grammar' => []]);

    (new AiContentAnalysisService($client, new ContentTokenizer, app(TracedLlmCall::class), app(PromptRegistryInterface::class)))->analyze($run);
})->throws(AiClientException::class, 'no lexemes or grammar');

test('analyze splits transcripts longer than the configured max into multiple chunks instead of truncating', function () {
    config(['ai.analysis.max_transcript_chars' => 20]);
    $run = makeAnalysisRun(str_repeat('word ', 50)); // 250 chars

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')
        ->atLeast()->times(2)
        ->with(Mockery::type('string'), Mockery::on(fn ($u) => mb_strlen($u) <= 20), Mockery::type('array'), Mockery::any())
        ->andReturn(['lexemes' => [['text' => 'word']], 'grammar' => []]);

    (new AiContentAnalysisService($client, new ContentTokenizer, app(TracedLlmCall::class), app(PromptRegistryInterface::class)))->analyze($run);

    // Every chunk found the same word — deduped to a single candidate row.
    expect($run->lexemeCandidates()->count())->toBe(1);
});

test('analyze merges and dedupes candidates found in more than one chunk', function () {
    config(['ai.analysis.max_transcript_chars' => 20]);
    $run = makeAnalysisRun(str_repeat('word ', 50));

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->andReturn(
        ['lexemes' => [['text' => 'word', 'translation' => 'слово']], 'grammar' => []],
        ['lexemes' => [['text' => 'WORD', 'translation' => 'should be ignored, first wins']], 'grammar' => []],
        ['lexemes' => [['text' => 'other', 'translation' => 'другое']], 'grammar' => []],
    );

    (new AiContentAnalysisService($client, new ContentTokenizer, app(TracedLlmCall::class), app(PromptRegistryInterface::class)))->analyze($run);

    expect($run->lexemeCandidates()->count())->toBe(2);
    expect($run->lexemeCandidates()->where('normalized_text', 'word')->first()->translation)->toBe('слово');
});

test('analyze persists the AI-supplied note on candidates', function () {
    $run = makeAnalysisRun();

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andReturn([
        'lexemes' => [['text' => 'get up', 'note' => 'Repeated in the chorus.']],
        'grammar' => [['title' => 'Present Perfect', 'note' => 'Used throughout the verse.']],
    ]);

    (new AiContentAnalysisService($client, new ContentTokenizer, app(TracedLlmCall::class), app(PromptRegistryInterface::class)))->analyze($run);

    expect($run->lexemeCandidates()->first()->note)->toBe('Repeated in the chorus.')
        ->and($run->grammarCandidates()->first()->note)->toBe('Used throughout the verse.');
});

test('analyze persists the AI-supplied grammar body and the prompt requests the structured format', function () {
    $run = makeAnalysisRun();

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')
        ->once()
        ->with(Mockery::on(fn ($s) => str_contains($s, '## Rule') && str_contains($s, '## Formation')), Mockery::type('string'), Mockery::type('array'), Mockery::any())
        ->andReturn([
            'lexemes' => [],
            'grammar' => [['title' => 'Present Perfect', 'body' => "## Rule\nUsed for...", 'summary' => 'Short summary.']],
        ]);

    (new AiContentAnalysisService($client, new ContentTokenizer, app(TracedLlmCall::class), app(PromptRegistryInterface::class)))->analyze($run);

    expect($run->grammarCandidates()->first()->body)->toBe("## Rule\nUsed for...");
});

test('analyze includes the target level clause in the prompt when configured', function () {
    $content = Content::query()->create([
        'type' => 'youtube', 'title' => 'Test', 'language' => 'en', 'origin' => 'curated', 'status' => 'ready', 'source_text' => 'text',
    ]);
    $run = $content->analysisRuns()->create(['status' => AiAnalysisRun::STATUS_PENDING, 'config' => ['target_level' => 'A2']]);

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')
        ->once()
        ->with(Mockery::on(fn ($s) => str_contains($s, 'A2 (CEFR) level of complexity')), Mockery::type('string'), Mockery::type('array'), Mockery::any())
        ->andReturn(['lexemes' => [['text' => 'word']], 'grammar' => []]);

    (new AiContentAnalysisService($client, new ContentTokenizer, app(TracedLlmCall::class), app(PromptRegistryInterface::class)))->analyze($run);
});

test('analyze asks for more extraction at a lower target level and includes the length-scaling instruction always', function () {
    $content = Content::query()->create([
        'type' => 'youtube', 'title' => 'Test', 'language' => 'en', 'origin' => 'curated', 'status' => 'ready', 'source_text' => 'text',
    ]);
    $run = $content->analysisRuns()->create(['status' => AiAnalysisRun::STATUS_PENDING, 'config' => ['target_level' => 'A2']]);

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')
        ->once()
        ->with(
            Mockery::on(fn ($s) => str_contains($s, "learner's overall level is A2") && str_contains($s, 'Scale the size of the word list')),
            Mockery::type('string'),
            Mockery::type('array'), Mockery::any()
        )
        ->andReturn(['lexemes' => [['text' => 'word']], 'grammar' => []]);

    (new AiContentAnalysisService($client, new ContentTokenizer, app(TracedLlmCall::class), app(PromptRegistryInterface::class)))->analyze($run);
});

test('analyze includes exclude words in the prompt when configured', function () {
    $content = Content::query()->create([
        'type' => 'youtube', 'title' => 'Test', 'language' => 'en', 'origin' => 'curated', 'status' => 'ready', 'source_text' => 'text',
    ]);
    $run = $content->analysisRuns()->create(['status' => AiAnalysisRun::STATUS_PENDING, 'config' => ['exclude_words' => ['run', 'jump']]]);

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')
        ->once()
        ->with(Mockery::on(fn ($s) => str_contains($s, 'run, jump')), Mockery::type('string'), Mockery::type('array'), Mockery::any())
        ->andReturn(['lexemes' => [['text' => 'word']], 'grammar' => []]);

    (new AiContentAnalysisService($client, new ContentTokenizer, app(TracedLlmCall::class), app(PromptRegistryInterface::class)))->analyze($run);
});

test('analyze includes resolved grammar rule titles, not raw ids, when excluded', function () {
    $content = Content::query()->create([
        'type' => 'youtube', 'title' => 'Test', 'language' => 'en', 'origin' => 'curated', 'status' => 'ready', 'source_text' => 'text',
    ]);
    $topic = GrammarTopic::query()->create(['slug' => 'topic-x', 'language' => 'en', 'name' => 'Topic X', 'status' => 'active']);
    $rule = GrammarRule::query()->create([
        'topic_id' => $topic->id, 'slug' => 'present-perfect', 'language' => 'en', 'title' => 'Present Perfect', 'status' => GrammarRule::STATUS_PUBLISHED,
    ]);
    $run = $content->analysisRuns()->create(['status' => AiAnalysisRun::STATUS_PENDING, 'config' => ['exclude_grammar_rule_ids' => [$rule->id]]]);

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')
        ->once()
        ->with(
            Mockery::on(fn ($s) => str_contains($s, 'already covered — do not propose them again: Present Perfect')),
            Mockery::type('string'),
            Mockery::type('array'), Mockery::any()
        )
        ->andReturn(['lexemes' => [['text' => 'word']], 'grammar' => []]);

    (new AiContentAnalysisService($client, new ContentTokenizer, app(TracedLlmCall::class), app(PromptRegistryInterface::class)))->analyze($run);
});

test('analyze switches to an exhaustive instruction when thoroughness is thorough', function () {
    $content = Content::query()->create([
        'type' => 'youtube', 'title' => 'Test', 'language' => 'en', 'origin' => 'curated', 'status' => 'ready', 'source_text' => 'text',
    ]);
    $run = $content->analysisRuns()->create(['status' => AiAnalysisRun::STATUS_PENDING, 'config' => ['thoroughness' => 'thorough']]);

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')
        ->once()
        ->with(
            Mockery::on(fn ($s) => str_contains($s, 'Be exhaustive with the vocabulary list')
                && str_contains($s, 'ordinary content words')
                && str_contains($s, 'learner level is unknown')),
            Mockery::type('string'),
            Mockery::type('array'), Mockery::any()
        )
        ->andReturn(['lexemes' => [['text' => 'word']], 'grammar' => []]);

    (new AiContentAnalysisService($client, new ContentTokenizer, app(TracedLlmCall::class), app(PromptRegistryInterface::class)))->analyze($run);
});

test('analyze includes extra instructions verbatim in the prompt', function () {
    $content = Content::query()->create([
        'type' => 'youtube', 'title' => 'Test', 'language' => 'en', 'origin' => 'curated', 'status' => 'ready', 'source_text' => 'text',
    ]);
    $run = $content->analysisRuns()->create(['status' => AiAnalysisRun::STATUS_PENDING, 'config' => ['extra_instructions' => 'Focus only on phrasal verbs.']]);

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')
        ->once()
        ->with(Mockery::on(fn ($s) => str_contains($s, 'Focus only on phrasal verbs.')), Mockery::type('string'), Mockery::type('array'), Mockery::any())
        ->andReturn(['lexemes' => [['text' => 'word']], 'grammar' => []]);

    (new AiContentAnalysisService($client, new ContentTokenizer, app(TracedLlmCall::class), app(PromptRegistryInterface::class)))->analyze($run);
});

test('analyze uses the per-run translation_language override in the prompt', function () {
    $content = Content::query()->create([
        'type' => 'youtube', 'title' => 'Test', 'language' => 'en', 'origin' => 'curated', 'status' => 'ready', 'source_text' => 'text',
    ]);
    $run = $content->analysisRuns()->create(['status' => AiAnalysisRun::STATUS_PENDING, 'config' => ['translation_language' => 'fr']]);

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')
        ->once()
        ->with(Mockery::on(fn ($s) => str_contains($s, 'translation into "fr"') && ! str_contains($s, 'translation into "ru"')), Mockery::type('string'), Mockery::type('array'), Mockery::any())
        ->andReturn(['lexemes' => [['text' => 'word']], 'grammar' => []]);

    (new AiContentAnalysisService($client, new ContentTokenizer, app(TracedLlmCall::class), app(PromptRegistryInterface::class)))->analyze($run);
});

test('analyze persists level, example_translation and a deterministic frequency count', function () {
    $run = makeAnalysisRun('I run every day. I love to run in the park.');

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andReturn([
        'lexemes' => [[
            'text' => 'run', 'level' => 'A1', 'example' => 'I run every day.', 'example_translation' => 'Я бегаю каждый день.',
        ]],
        'grammar' => [['title' => 'Present Simple', 'example' => 'I run every day.', 'example_translation' => 'Я бегаю каждый день.']],
    ]);

    (new AiContentAnalysisService($client, new ContentTokenizer, app(TracedLlmCall::class), app(PromptRegistryInterface::class)))->analyze($run);

    $lexeme = $run->lexemeCandidates()->first();
    expect($lexeme->level)->toBe('A1')
        ->and($lexeme->example_translation)->toBe('Я бегаю каждый день.')
        ->and($lexeme->frequency)->toBe(2);

    expect($run->grammarCandidates()->first()->example_translation)->toBe('Я бегаю каждый день.');
});

test('analyze drops an invalid CEFR level to null', function () {
    $run = makeAnalysisRun();

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andReturn([
        'lexemes' => [['text' => 'word', 'level' => 'not-a-level']],
        'grammar' => [],
    ]);

    (new AiContentAnalysisService($client, new ContentTokenizer, app(TracedLlmCall::class), app(PromptRegistryInterface::class)))->analyze($run);

    expect($run->lexemeCandidates()->first()->level)->toBeNull();
});

test('analyze persists lemma and grammar features separately from the occurrence text (task 10.1)', function () {
    $run = makeAnalysisRun('He ran across the street.');

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andReturn([
        'lexemes' => [[
            'text' => 'ran', 'lemma' => 'run', 'type' => 'word',
            'grammar' => ['tense' => 'past', 'is_irregular' => true, 'unknown_key' => 'dropped'],
        ]],
        'grammar' => [],
    ]);

    (new AiContentAnalysisService($client, new ContentTokenizer, app(TracedLlmCall::class), app(PromptRegistryInterface::class)))->analyze($run);

    $candidate = $run->lexemeCandidates()->first();
    expect($candidate->text)->toBe('ran')
        ->and($candidate->lemma)->toBe('run')
        ->and($candidate->normalized_lemma)->toBe('run')
        ->and($candidate->grammar_features)->toBe(['tense' => 'past', 'is_irregular' => true]);
});

test('analyze falls back to the occurrence text as the lemma when the AI omits lemma', function () {
    $run = makeAnalysisRun();

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andReturn([
        'lexemes' => [['text' => 'word']],
        'grammar' => [],
    ]);

    (new AiContentAnalysisService($client, new ContentTokenizer, app(TracedLlmCall::class), app(PromptRegistryInterface::class)))->analyze($run);

    $candidate = $run->lexemeCandidates()->first();
    expect($candidate->lemma)->toBe('word')
        ->and($candidate->normalized_lemma)->toBe('word')
        ->and($candidate->grammar_features)->toBeNull();
});

test('analyze persists part_of_speech from the AI response (task 10.2)', function () {
    $run = makeAnalysisRun();

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andReturn([
        'lexemes' => [['text' => 'run', 'lemma' => 'run', 'part_of_speech' => 'verb']],
        'grammar' => [],
    ]);

    (new AiContentAnalysisService($client, new ContentTokenizer, app(TracedLlmCall::class), app(PromptRegistryInterface::class)))->analyze($run);

    expect($run->lexemeCandidates()->first()->part_of_speech)->toBe('verb');
});

test('analyze drops an unrecognized part_of_speech value to null', function () {
    $run = makeAnalysisRun();

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andReturn([
        'lexemes' => [['text' => 'word', 'part_of_speech' => 'not-a-real-pos']],
        'grammar' => [],
    ]);

    (new AiContentAnalysisService($client, new ContentTokenizer, app(TracedLlmCall::class), app(PromptRegistryInterface::class)))->analyze($run);

    expect($run->lexemeCandidates()->first()->part_of_speech)->toBeNull();
});

test('analyze persists the sense gloss from the AI response (task 10.3)', function () {
    $run = makeAnalysisRun();

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andReturn([
        'lexemes' => [['text' => 'run', 'lemma' => 'run', 'sense' => 'move quickly on foot']],
        'grammar' => [],
    ]);

    (new AiContentAnalysisService($client, new ContentTokenizer, app(TracedLlmCall::class), app(PromptRegistryInterface::class)))->analyze($run);

    expect($run->lexemeCandidates()->first()->sense)->toBe('move quickly on foot');
});

test('analyze leaves sense null when the AI omits it (single-meaning lemma)', function () {
    $run = makeAnalysisRun();

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andReturn([
        'lexemes' => [['text' => 'ubiquitous']],
        'grammar' => [],
    ]);

    (new AiContentAnalysisService($client, new ContentTokenizer, app(TracedLlmCall::class), app(PromptRegistryInterface::class)))->analyze($run);

    expect($run->lexemeCandidates()->first()->sense)->toBeNull();
});

test('analyze does not count a word that only appears as a substring of another word', function () {
    $run = makeAnalysisRun('The cat sat on the mat. Category theory is unrelated.');

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andReturn([
        'lexemes' => [['text' => 'cat']],
        'grammar' => [],
    ]);

    (new AiContentAnalysisService($client, new ContentTokenizer, app(TracedLlmCall::class), app(PromptRegistryInterface::class)))->analyze($run);

    // "cat" appears once as a whole word; "Category" must not count as a match.
    expect($run->lexemeCandidates()->first()->frequency)->toBe(1);
});

test('coverage at or above threshold is stored and does not trigger a retry', function () {
    config(['ai.analysis.min_coverage_pct' => 0.7]);
    $run = makeAnalysisRun('alpha beta');

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andReturn([
        'lexemes' => [['text' => 'alpha beta']],
        'grammar' => [],
    ]);

    (new AiContentAnalysisService($client, new ContentTokenizer, app(TracedLlmCall::class), app(PromptRegistryInterface::class)))->analyze($run);

    $run->refresh();
    expect($run->coverage_pct)->toBe(100.0)
        ->and($run->uncovered_words)->toBe([])
        ->and($run->retried_for_coverage)->toBeFalse();
});

test('low coverage triggers exactly one auto-retry with thoroughness=thorough', function () {
    config(['ai.analysis.min_coverage_pct' => 0.7]);
    $run = makeAnalysisRun('alpha beta gamma delta epsilon');

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')
        ->twice()
        ->andReturn(
            ['lexemes' => [['text' => 'alpha']], 'grammar' => []],
            ['lexemes' => [['text' => 'alpha beta gamma delta epsilon']], 'grammar' => []],
        );

    (new AiContentAnalysisService($client, new ContentTokenizer, app(TracedLlmCall::class), app(PromptRegistryInterface::class)))->analyze($run);

    $run->refresh();
    expect($run->retried_for_coverage)->toBeTrue()
        ->and($run->config['thoroughness'])->toBe('thorough')
        ->and($run->coverage_pct)->toBe(100.0)
        ->and($run->uncovered_words)->toBe([])
        // The low-coverage first pass's candidate is wiped, not left behind
        // alongside the thorough retry's replacement.
        ->and($run->lexemeCandidates()->count())->toBe(1);
});

test('a coverage-triggered retry never triggers a second retry even if still below threshold', function () {
    config(['ai.analysis.min_coverage_pct' => 0.7]);
    $run = makeAnalysisRun('alpha beta gamma delta epsilon');

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')
        ->twice()
        ->andReturn(
            ['lexemes' => [['text' => 'alpha']], 'grammar' => []],
            ['lexemes' => [['text' => 'beta']], 'grammar' => []],
        );

    (new AiContentAnalysisService($client, new ContentTokenizer, app(TracedLlmCall::class), app(PromptRegistryInterface::class)))->analyze($run);

    $run->refresh();
    expect($run->retried_for_coverage)->toBeTrue()
        ->and($run->coverage_pct)->toBe(20.0)
        ->and($run->uncovered_words)->toBe(['alpha', 'gamma', 'delta', 'epsilon'])
        ->and($run->lexemeCandidates()->count())->toBe(1)
        ->and($run->lexemeCandidates()->first()->text)->toBe('beta');
});

test('analyze persists multiple examples per lexeme candidate, tagged by source (task 9.9)', function () {
    $run = makeAnalysisRun();

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andReturn([
        'lexemes' => [[
            'text' => 'get up',
            'type' => 'phrasal_verb',
            'examples' => [
                ['text' => 'Time to get up.', 'translation' => 'Пора вставать.', 'source' => 'context'],
                ['text' => 'I get up early every day.', 'translation' => 'Я встаю рано каждый день.', 'source' => 'generated'],
            ],
        ]],
        'grammar' => [],
    ]);

    (new AiContentAnalysisService($client, new ContentTokenizer, app(TracedLlmCall::class), app(PromptRegistryInterface::class)))->analyze($run);

    $candidate = $run->lexemeCandidates()->first();
    expect($candidate->examples)->toHaveCount(2)
        ->and($candidate->examples[0])->toBe(['text' => 'Time to get up.', 'translation' => 'Пора вставать.', 'source' => 'context'])
        ->and($candidate->examples[1]['source'])->toBe('generated')
        // Backward-compat mirror columns take the context-sourced example.
        ->and($candidate->example)->toBe('Time to get up.')
        ->and($candidate->example_translation)->toBe('Пора вставать.');
});

test('analyze mirrors the first example when none of them are tagged context', function () {
    $run = makeAnalysisRun();

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andReturn([
        'lexemes' => [[
            'text' => 'word',
            'examples' => [
                ['text' => 'A generated sentence.', 'translation' => 'Придуманное предложение.', 'source' => 'generated'],
            ],
        ]],
        'grammar' => [],
    ]);

    (new AiContentAnalysisService($client, new ContentTokenizer, app(TracedLlmCall::class), app(PromptRegistryInterface::class)))->analyze($run);

    $candidate = $run->lexemeCandidates()->first();
    expect($candidate->example)->toBe('A generated sentence.');
});

test('analyze falls back to a single context example when the AI still returns the old singular example shape', function () {
    $run = makeAnalysisRun();

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andReturn([
        'lexemes' => [['text' => 'word', 'example' => 'Legacy shape example.', 'example_translation' => 'Пример старого формата.']],
        'grammar' => [],
    ]);

    (new AiContentAnalysisService($client, new ContentTokenizer, app(TracedLlmCall::class), app(PromptRegistryInterface::class)))->analyze($run);

    $candidate = $run->lexemeCandidates()->first();
    expect($candidate->examples)->toBe([
        ['text' => 'Legacy shape example.', 'translation' => 'Пример старого формата.', 'source' => 'context'],
    ])
        ->and($candidate->example)->toBe('Legacy shape example.')
        ->and($candidate->example_translation)->toBe('Пример старого формата.');
});

test('analyze skips malformed entries in the examples array', function () {
    $run = makeAnalysisRun();

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andReturn([
        'lexemes' => [[
            'text' => 'word',
            'examples' => [
                ['translation' => 'no text field'],
                ['text' => '', 'translation' => 'empty text'],
                ['text' => 'Valid one.', 'translation' => 'Валидный.', 'source' => 'context'],
            ],
        ]],
        'grammar' => [],
    ]);

    (new AiContentAnalysisService($client, new ContentTokenizer, app(TracedLlmCall::class), app(PromptRegistryInterface::class)))->analyze($run);

    $candidate = $run->lexemeCandidates()->first();
    expect($candidate->examples)->toBe([
        ['text' => 'Valid one.', 'translation' => 'Валидный.', 'source' => 'context'],
    ]);
});

test('analyze records an llm_call span with usage and cost when tracing is enabled', function () {
    Config::set('ai.tracing.enabled', true);
    Config::set('ai.pricing.prompt_per_1k_usd', 0.001);
    Config::set('ai.pricing.completion_per_1k_usd', 0.002);

    $run = makeAnalysisRun();

    $client = Mockery::mock(AiJsonClient::class, AiUsageReportingClient::class);
    $client->shouldReceive('completeJson')->once()->andReturn(['lexemes' => [], 'grammar' => [
        ['title' => 'Present Perfect', 'summary' => 'x'],
    ]]);
    $client->shouldReceive('lastUsage')->once()->andReturn(new TokenUsage(1000, 500));
    $client->shouldReceive('lastModel')->once()->andReturn('gpt-4o-mini');

    (new AiContentAnalysisService($client, new ContentTokenizer, app(TracedLlmCall::class), app(PromptRegistryInterface::class)))->analyze($run);

    $span = AgentTraceSpan::query()->where('name', 'content_analysis.completeJson')->first();

    expect($span)->not->toBeNull()
        ->and($span->span_type)->toBe(AgentTraceSpan::SPAN_TYPE_LLM_CALL)
        ->and($span->status)->toBe('ok')
        ->and($span->prompt_tokens)->toBe(1000)
        ->and($span->completion_tokens)->toBe(500)
        ->and($span->model)->toBe('gpt-4o-mini')
        ->and($span->cost_usd)->toBe(0.001 + 0.001) // 1000/1k * 0.001 + 500/1k * 0.002
        ->and($span->metadata['run_id'])->toBe($run->id)
        ->and($span->metadata['feature'])->toBe('content_analysis');
});

test('analyze does not record any span when tracing is disabled (default)', function () {
    $run = makeAnalysisRun();

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andReturn(['lexemes' => [], 'grammar' => [
        ['title' => 'Present Perfect', 'summary' => 'x'],
    ]]);

    (new AiContentAnalysisService($client, new ContentTokenizer, app(TracedLlmCall::class), app(PromptRegistryInterface::class)))->analyze($run);

    expect(AgentTraceSpan::query()->count())->toBe(0);
});

test('analyze marks the span failed and rethrows when completeJson throws', function () {
    Config::set('ai.tracing.enabled', true);
    $run = makeAnalysisRun();

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andThrow(new AiClientException('boom'));

    expect(fn () => (new AiContentAnalysisService($client, new ContentTokenizer, app(TracedLlmCall::class), app(PromptRegistryInterface::class)))->analyze($run))
        ->toThrow(AiClientException::class, 'boom');

    $span = AgentTraceSpan::query()->where('name', 'content_analysis.completeJson')->first();
    expect($span)->not->toBeNull()
        ->and($span->status)->toBe('error');
});

test('a published content_analysis_system_prompt override with {{#if}} blocks replaces the default prompt, conditionals resolved from real run config', function () {
    $template = PromptTemplate::query()->create(['key' => 'content_analysis_system_prompt', 'name' => 'Content analysis prompt']);
    $version = $template->versions()->create([
        'version' => 1,
        'system_template' => 'Analyze in {{source_language}}, translate to {{translation_language}}.'
            .'{{#if thorough}} Be exhaustive.{{else}} Stay concise.{{/if}}'
            .'{{#if target_level}} Target level: {{target_level}}.{{/if}}'
            .'{{#if exclude_words}} Skip: {{exclude_words}}.{{/if}}',
        'user_template' => '',
    ]);
    $template->update(['active_version_id' => $version->id]);

    $run = makeAnalysisRun();
    $run->update(['config' => ['target_level' => 'B1', 'exclude_words' => ['run', 'jump'], 'thoroughness' => 'thorough']]);

    $sentSystemPrompt = null;
    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')
        ->once()
        ->andReturnUsing(function ($system) use (&$sentSystemPrompt) {
            $sentSystemPrompt = $system;

            return ['lexemes' => [['text' => 'run', 'type' => 'word']], 'grammar' => []];
        });

    (new AiContentAnalysisService($client, new ContentTokenizer, app(TracedLlmCall::class), app(PromptRegistryInterface::class)))->analyze($run);

    expect($sentSystemPrompt)
        ->toContain('You are a language-learning content analyst')
        ->toContain('Additional active prompt instructions:')
        ->toContain('Analyze in en, translate to ru. Be exhaustive. Target level: B1. Skip: run, jump.')
        ->toContain('every lexeme translation and every example translation must be written in ru');
});

test('without an override, analyze() still uses the full hand-written default prompt (conditionals included)', function () {
    $run = makeAnalysisRun();

    $sentSystemPrompt = null;
    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')
        ->once()
        ->andReturnUsing(function ($system) use (&$sentSystemPrompt) {
            $sentSystemPrompt = $system;

            return ['lexemes' => [['text' => 'run', 'type' => 'word']], 'grammar' => []];
        });

    (new AiContentAnalysisService($client, new ContentTokenizer, app(TracedLlmCall::class), app(PromptRegistryInterface::class)))->analyze($run);

    expect($sentSystemPrompt)->toContain('language-learning content analyst');
});
