<?php

use App\Modules\Ai\Application\AiCandidateApplyService;
use App\Modules\Ai\Domain\Models\AiAnalysisRun;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\ContentRuleLink;
use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Domain\Models\GrammarRuleExample;
use App\Modules\Content\Domain\Models\GrammarTopic;
use App\Modules\Content\Domain\Models\Lexeme;
use App\Modules\Content\Domain\Models\LexemeExample;
use App\Modules\Content\Domain\Models\LexemeSense;
use App\Modules\Content\Domain\Models\LexemeTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeApplyRun(): AiAnalysisRun
{
    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'Apply test',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
        'source_text' => 'text',
    ]);

    return $content->analysisRuns()->create(['status' => AiAnalysisRun::STATUS_COMPLETED]);
}

function contentForApplyRun(AiAnalysisRun $run): Content
{
    return Content::query()->findOrFail($run->content_id);
}

test('apply creates a canonical lexeme and link when candidate has no match', function () {
    $run = makeApplyRun();
    $candidate = $run->lexemeCandidates()->create([
        'text' => 'get up', 'normalized_text' => 'get up', 'type' => 'phrasal_verb', 'status' => 'accepted',
    ]);

    $result = app(AiCandidateApplyService::class)->apply($run);

    expect($result)->toBe(['lexemes' => 1, 'grammar' => 0]);
    $candidate->refresh();
    expect($candidate->status)->toBe('applied');

    $lexeme = Lexeme::query()->where('language', 'en')->where('normalized_lemma', 'get up')->first();
    expect($lexeme)->not->toBeNull();
    expect(contentForApplyRun($run)->lexemes()->where('text', 'get up')->where('lexeme_id', $lexeme->id)->exists())->toBeTrue();
    expect(contentForApplyRun($run)->lexemes()->where('text', 'get up')->value('origin'))->toBe(\App\Modules\Content\Domain\Models\ContentLexeme::ORIGIN_AI);
});

test('apply links directly to the matched lexeme without creating a duplicate', function () {
    $run = makeApplyRun();
    $existing = Lexeme::query()->create([
        'slug' => 'en-getting-up', 'language' => 'en', 'lemma' => 'getting up', 'normalized_lemma' => 'getting up', 'status' => Lexeme::STATUS_PUBLISHED,
    ]);
    $candidate = $run->lexemeCandidates()->create([
        'text' => 'get up', 'normalized_text' => 'get up', 'type' => 'phrasal_verb', 'status' => 'accepted',
        'matched_lexeme_id' => $existing->id, 'match_score' => 0.9,
    ]);

    $countBefore = Lexeme::query()->count();
    app(AiCandidateApplyService::class)->apply($run);
    expect(Lexeme::query()->count())->toBe($countBefore);

    // Also creates the occurrence row now (not just a bare link), so this word
    // actually shows up in the content's word list (StudyPage), not just in
    // the canonical catalog.
    expect(contentForApplyRun($run)->lexemes()->where('text', 'get up')->where('lexeme_id', $existing->id)->exists())->toBeTrue();
    expect(contentForApplyRun($run)->lexemes()->where('text', 'get up')->value('origin'))->toBe(\App\Modules\Content\Domain\Models\ContentLexeme::ORIGIN_AI);
    expect($candidate->refresh()->status)->toBe('applied');
});

test('apply creates a new grammar rule under the AI Suggested topic when no match', function () {
    $run = makeApplyRun();
    $candidate = $run->grammarCandidates()->create([
        'title' => 'Present Perfect Continuous', 'summary' => 'Unfinished past action.', 'body' => "## Rule\nUnfinished action.", 'status' => 'accepted',
    ]);

    $result = app(AiCandidateApplyService::class)->apply($run);

    expect($result)->toBe(['lexemes' => 0, 'grammar' => 1]);
    $rule = GrammarRule::query()->where('title', 'Present Perfect Continuous')->first();
    expect($rule)->not->toBeNull()
        ->and($rule->status)->toBe(GrammarRule::STATUS_PUBLISHED)
        ->and($rule->body)->toBe("## Rule\nUnfinished action.")
        ->and($rule->topic->name)->toBe('AI Suggested');
    expect(ContentRuleLink::query()->where('content_id', $run->content_id)->where('grammar_rule_id', $rule->id)->exists())->toBeTrue();
    expect($candidate->refresh()->status)->toBe('applied');
});

test('apply does not overwrite an existing grammar rule body when linking to a match', function () {
    $run = makeApplyRun();
    $topic = GrammarTopic::query()->create(['slug' => 'perfect-tenses', 'language' => 'en', 'name' => 'Perfect tenses', 'status' => 'active']);
    $existing = GrammarRule::query()->create([
        'topic_id' => $topic->id, 'slug' => 'present-perfect', 'language' => 'en', 'title' => 'Present Perfect', 'status' => GrammarRule::STATUS_PUBLISHED,
        'body' => 'Curated body — must not change.',
    ]);
    $run->grammarCandidates()->create([
        'title' => 'Present Perfect', 'body' => 'AI proposed body.', 'status' => 'accepted', 'matched_grammar_rule_id' => $existing->id, 'match_score' => 1.0,
    ]);

    app(AiCandidateApplyService::class)->apply($run);

    expect($existing->fresh()->body)->toBe('Curated body — must not change.');
});

test('apply reuses the AI Suggested topic across multiple runs instead of creating duplicates', function () {
    $run1 = makeApplyRun();
    $run1->grammarCandidates()->create(['title' => 'Rule A', 'status' => 'accepted']);
    app(AiCandidateApplyService::class)->apply($run1);

    $run2 = makeApplyRun();
    $run2->grammarCandidates()->create(['title' => 'Rule B', 'status' => 'accepted']);
    app(AiCandidateApplyService::class)->apply($run2);

    expect(GrammarTopic::query()->where('slug', 'ai-suggested-en')->count())->toBe(1);
});

test('apply links directly to the matched grammar rule without creating a duplicate', function () {
    $run = makeApplyRun();
    $topic = GrammarTopic::query()->create(['slug' => 'perfect-tenses', 'language' => 'en', 'name' => 'Perfect tenses', 'status' => 'active']);
    $existing = GrammarRule::query()->create([
        'topic_id' => $topic->id, 'slug' => 'present-perfect', 'language' => 'en', 'title' => 'Present Perfect', 'status' => GrammarRule::STATUS_PUBLISHED,
    ]);
    $candidate = $run->grammarCandidates()->create([
        'title' => 'Present Perfect', 'status' => 'accepted', 'matched_grammar_rule_id' => $existing->id, 'match_score' => 1.0,
    ]);

    $countBefore = GrammarRule::query()->count();
    app(AiCandidateApplyService::class)->apply($run);

    expect(GrammarRule::query()->count())->toBe($countBefore);
    expect(ContentRuleLink::query()->where('content_id', $run->content_id)->where('grammar_rule_id', $existing->id)->exists())->toBeTrue();
    expect($candidate->refresh()->status)->toBe('applied');
});

test('apply ignores pending and rejected candidates', function () {
    $run = makeApplyRun();
    $run->lexemeCandidates()->create(['text' => 'pending one', 'normalized_text' => 'pending one', 'type' => 'word', 'status' => 'pending']);
    $run->lexemeCandidates()->create(['text' => 'rejected one', 'normalized_text' => 'rejected one', 'type' => 'word', 'status' => 'rejected']);

    $result = app(AiCandidateApplyService::class)->apply($run);

    expect($result)->toBe(['lexemes' => 0, 'grammar' => 0]);
    expect(Lexeme::query()->count())->toBe(0);
});

test('apply stores the example and its translation as a primary LexemeExample for a new lexeme', function () {
    $run = makeApplyRun();
    $candidate = $run->lexemeCandidates()->create([
        'text' => 'get up', 'normalized_text' => 'get up', 'type' => 'phrasal_verb', 'status' => 'accepted',
        'translation' => 'вставать', 'example' => 'Get up, it is late!', 'example_translation' => 'Вставай, уже поздно!',
    ]);

    app(AiCandidateApplyService::class)->apply($run);

    $lexeme = Lexeme::query()->where('normalized_lemma', 'get up')->first();
    $example = LexemeExample::query()->where('lexeme_id', $lexeme->id)->first();
    expect($example)->not->toBeNull()
        ->and($example->translation)->toBe('Вставай, уже поздно!')
        ->and($example->translation_language)->toBe('ru')
        ->and($example->example)->toBe('Get up, it is late!')
        ->and($example->is_primary)->toBeTrue()
        ->and($example->language)->toBe('en')
        ->and($example->content_id)->toBe($run->content_id);
});

test('apply creates one LexemeExample per entry in examples, with the context-sourced one primary', function () {
    $run = makeApplyRun();
    $candidate = $run->lexemeCandidates()->create([
        'text' => 'get up', 'normalized_text' => 'get up', 'type' => 'phrasal_verb', 'status' => 'accepted',
        'translation' => 'вставать',
        'examples' => [
            ['text' => 'I get up at seven.', 'translation' => 'Я встаю в семь.', 'source' => 'generated'],
            ['text' => 'Get up, it is late!', 'translation' => 'Вставай, уже поздно!', 'source' => 'context'],
        ],
    ]);

    app(AiCandidateApplyService::class)->apply($run);

    $lexeme = Lexeme::query()->where('normalized_lemma', 'get up')->first();
    $examples = LexemeExample::query()->where('lexeme_id', $lexeme->id)->orderBy('sort_order')->get();

    expect($examples)->toHaveCount(2);
    $generated = $examples->firstWhere('example', 'I get up at seven.');
    $context = $examples->firstWhere('example', 'Get up, it is late!');
    expect($generated->is_primary)->toBeFalse()
        ->and($context->is_primary)->toBeTrue()
        ->and($context->translation)->toBe('Вставай, уже поздно!')
        ->and($candidate->fresh()->status)->toBe('applied');
});

test('apply picks the first example as primary when none of them are context-sourced', function () {
    $run = makeApplyRun();
    $run->lexemeCandidates()->create([
        'text' => 'walk', 'normalized_text' => 'walk', 'type' => 'word', 'status' => 'accepted',
        'examples' => [
            ['text' => 'First generated example.', 'translation' => null, 'source' => 'generated'],
            ['text' => 'Second generated example.', 'translation' => null, 'source' => 'generated'],
        ],
    ]);

    app(AiCandidateApplyService::class)->apply($run);

    $lexeme = Lexeme::query()->where('normalized_lemma', 'walk')->first();
    $primary = LexemeExample::query()->where('lexeme_id', $lexeme->id)->where('is_primary', true)->first();
    expect($primary->example)->toBe('First generated example.');
});

test('apply falls back to the legacy singular example for a candidate created before the examples column existed', function () {
    $run = makeApplyRun();
    $run->lexemeCandidates()->create([
        'text' => 'legacy', 'normalized_text' => 'legacy', 'type' => 'word', 'status' => 'accepted',
        'example' => 'A legacy-shape example.', 'example_translation' => 'Пример старого формата.',
        'examples' => null,
    ]);

    app(AiCandidateApplyService::class)->apply($run);

    $lexeme = Lexeme::query()->where('normalized_lemma', 'legacy')->first();
    $examples = LexemeExample::query()->where('lexeme_id', $lexeme->id)->get();
    expect($examples)->toHaveCount(1)
        ->and($examples->first()->example)->toBe('A legacy-shape example.')
        ->and($examples->first()->is_primary)->toBeTrue();
});

test('apply stores the word gloss as a LexemeTranslation, separate from the example translation', function () {
    $run = makeApplyRun();
    $run->lexemeCandidates()->create([
        'text' => 'get up', 'normalized_text' => 'get up', 'type' => 'phrasal_verb', 'status' => 'accepted',
        'translation' => 'вставать', 'example' => 'Get up, it is late!', 'example_translation' => 'Вставай, уже поздно!',
    ]);

    app(AiCandidateApplyService::class)->apply($run);

    $lexeme = Lexeme::query()->where('normalized_lemma', 'get up')->first();
    $translation = LexemeTranslation::query()->where('lexeme_id', $lexeme->id)->first();
    expect($translation)->not->toBeNull()
        ->and($translation->translation)->toBe('вставать')
        ->and($translation->language)->toBe('ru')
        ->and($translation->is_primary)->toBeTrue()
        ->and($translation->content_id)->toBe($run->content_id);
});

test('apply stores the gloss for a matched lexeme too, not only new ones', function () {
    $run = makeApplyRun();
    $existing = Lexeme::query()->create([
        'slug' => 'en-getting-up', 'language' => 'en', 'lemma' => 'getting up', 'normalized_lemma' => 'getting up', 'status' => Lexeme::STATUS_PUBLISHED,
    ]);
    $run->lexemeCandidates()->create([
        'text' => 'get up', 'normalized_text' => 'get up', 'type' => 'phrasal_verb', 'status' => 'accepted',
        'matched_lexeme_id' => $existing->id, 'translation' => 'вставать', 'example' => 'Get up now.',
    ]);

    app(AiCandidateApplyService::class)->apply($run);

    $translation = LexemeTranslation::query()->where('lexeme_id', $existing->id)->where('translation', 'вставать')->first();
    expect($translation)->not->toBeNull()
        ->and($translation->content_id)->toBe($run->content_id);
});

test('apply respects a per-run translation_language override', function () {
    $content = Content::query()->create([
        'type' => 'youtube', 'title' => 'Apply test', 'language' => 'en', 'origin' => 'curated', 'status' => 'ready', 'source_text' => 'text',
    ]);
    $run = $content->analysisRuns()->create(['status' => AiAnalysisRun::STATUS_COMPLETED, 'config' => ['translation_language' => 'fr']]);
    $run->lexemeCandidates()->create([
        'text' => 'get up', 'normalized_text' => 'get up', 'type' => 'phrasal_verb', 'status' => 'accepted', 'translation' => 'se lever',
    ]);

    app(AiCandidateApplyService::class)->apply($run);

    $lexeme = Lexeme::query()->where('normalized_lemma', 'get up')->first();
    expect(LexemeTranslation::query()->where('lexeme_id', $lexeme->id)->first()->language)->toBe('fr');
});

test('apply does not create a LexemeExample when candidate has neither example nor example_translation', function () {
    $run = makeApplyRun();
    $run->lexemeCandidates()->create([
        'text' => 'run', 'normalized_text' => 'run', 'type' => 'word', 'status' => 'accepted', 'translation' => 'бежать',
    ]);

    app(AiCandidateApplyService::class)->apply($run);

    expect(LexemeExample::query()->count())->toBe(0);
    // The word gloss is unaffected by the missing example.
    expect(LexemeTranslation::query()->count())->toBe(1);
});

test('apply does not create a LexemeTranslation when candidate has no translation', function () {
    $run = makeApplyRun();
    $run->lexemeCandidates()->create([
        'text' => 'run', 'normalized_text' => 'run', 'type' => 'word', 'status' => 'accepted', 'example' => 'I run every morning.',
    ]);

    app(AiCandidateApplyService::class)->apply($run);

    expect(LexemeTranslation::query()->count())->toBe(0);
});

test('apply backfills level on a newly created lexeme', function () {
    $run = makeApplyRun();
    $run->lexemeCandidates()->create([
        'text' => 'ubiquitous', 'normalized_text' => 'ubiquitous', 'type' => 'word', 'status' => 'accepted', 'level' => 'C1',
    ]);

    app(AiCandidateApplyService::class)->apply($run);

    expect(Lexeme::query()->where('normalized_lemma', 'ubiquitous')->first()->level)->toBe('C1');
});

test('apply never overwrites an existing curated level on a matched lexeme', function () {
    $run = makeApplyRun();
    $existing = Lexeme::query()->create([
        'slug' => 'en-run', 'language' => 'en', 'lemma' => 'run', 'normalized_lemma' => 'run', 'status' => Lexeme::STATUS_PUBLISHED, 'level' => 'A1',
    ]);
    $run->lexemeCandidates()->create([
        'text' => 'run', 'normalized_text' => 'run', 'type' => 'word', 'status' => 'accepted',
        'matched_lexeme_id' => $existing->id, 'level' => 'C2',
    ]);

    app(AiCandidateApplyService::class)->apply($run);

    expect($existing->fresh()->level)->toBe('A1');
});

test('apply persists frequency onto the new ContentLexeme occurrence', function () {
    $run = makeApplyRun();
    $run->lexemeCandidates()->create([
        'text' => 'run', 'normalized_text' => 'run', 'type' => 'word', 'status' => 'accepted', 'frequency' => 4,
    ]);

    app(AiCandidateApplyService::class)->apply($run);

    expect(contentForApplyRun($run)->lexemes()->where('text', 'run')->first()->frequency)->toBe(4);
});

test('apply stores example and its translation as a primary GrammarRuleExample for a new rule', function () {
    $run = makeApplyRun();
    $run->grammarCandidates()->create([
        'title' => 'First Conditional', 'summary' => 'Real future situations.',
        'example' => 'If it rains, I will stay home.', 'example_translation' => 'Если пойдёт дождь, я останусь дома.', 'status' => 'accepted',
    ]);

    app(AiCandidateApplyService::class)->apply($run);

    $rule = GrammarRule::query()->where('title', 'First Conditional')->first();
    $example = GrammarRuleExample::query()->where('grammar_rule_id', $rule->id)->first();
    expect($example)->not->toBeNull()
        ->and($example->example)->toBe('If it rains, I will stay home.')
        ->and($example->translation)->toBe('Если пойдёт дождь, я останусь дома.')
        ->and($example->translation_language)->toBe('ru')
        ->and($example->is_primary)->toBeTrue()
        ->and($example->content_id)->toBe($run->content_id);
});

test('a globally curated example (no content_id) coexists with content-scoped ones from Apply', function () {
    $run = makeApplyRun();
    $candidate = $run->lexemeCandidates()->create([
        'text' => 'get up', 'normalized_text' => 'get up', 'type' => 'phrasal_verb', 'status' => 'accepted',
        'translation' => 'вставать', 'example' => 'Get up now.',
    ]);

    app(AiCandidateApplyService::class)->apply($run);
    $lexeme = Lexeme::query()->where('normalized_lemma', 'get up')->first();

    // Simulates an admin manually curating a general-purpose example unrelated to any content.
    $lexeme->examples()->create([
        'language' => 'en', 'example' => 'Get up early every day.', 'translation' => 'вставай рано каждый день', 'is_primary' => false, 'sort_order' => 10,
    ]);

    expect(LexemeExample::query()->where('lexeme_id', $lexeme->id)->whereNull('content_id')->count())->toBe(1)
        ->and(LexemeExample::query()->where('lexeme_id', $lexeme->id)->where('content_id', $run->content_id)->count())->toBe(1);
});

test('apply resolves an inflected occurrence onto its own lemma, not a new one (task 10.1)', function () {
    $run = makeApplyRun();
    $run->lexemeCandidates()->create([
        'text' => 'ran', 'normalized_text' => 'ran', 'lemma' => 'run', 'normalized_lemma' => 'run',
        'type' => 'word', 'status' => 'accepted', 'grammar_features' => ['tense' => 'past'],
    ]);

    app(AiCandidateApplyService::class)->apply($run);

    $lexeme = Lexeme::query()->where('normalized_lemma', 'run')->first();
    expect($lexeme)->not->toBeNull()
        ->and($lexeme->lemma)->toBe('run');
    expect(Lexeme::query()->where('normalized_lemma', 'ran')->exists())->toBeFalse();

    $occurrence = contentForApplyRun($run)->lexemes()->where('text', 'ran')->first();
    expect($occurrence)->not->toBeNull()
        ->and($occurrence->lexeme_id)->toBe($lexeme->id)
        ->and($occurrence->grammar_features)->toBe(['tense' => 'past']);
});

test('apply collapses two different inflected forms of the same lemma from separate runs onto one Lexeme', function () {
    $run1 = makeApplyRun();
    $run1->lexemeCandidates()->create([
        'text' => 'ran', 'normalized_text' => 'ran', 'lemma' => 'run', 'normalized_lemma' => 'run', 'type' => 'word', 'status' => 'accepted',
    ]);
    app(AiCandidateApplyService::class)->apply($run1);

    $run2 = makeApplyRun();
    $run2->lexemeCandidates()->create([
        'text' => 'running', 'normalized_text' => 'running', 'lemma' => 'run', 'normalized_lemma' => 'run', 'type' => 'word', 'status' => 'accepted',
    ]);
    app(AiCandidateApplyService::class)->apply($run2);

    expect(Lexeme::query()->where('normalized_lemma', 'run')->count())->toBe(1);
});

test('apply falls back to the occurrence text as the lemma when the candidate has none (legacy shape)', function () {
    $run = makeApplyRun();
    $run->lexemeCandidates()->create([
        'text' => 'word', 'normalized_text' => 'word', 'type' => 'word', 'status' => 'accepted',
    ]);

    app(AiCandidateApplyService::class)->apply($run);

    expect(Lexeme::query()->where('normalized_lemma', 'word')->exists())->toBeTrue();
});

test('apply sets part_of_speech on a newly created lexeme (task 10.2)', function () {
    $run = makeApplyRun();
    $run->lexemeCandidates()->create([
        'text' => 'run', 'normalized_text' => 'run', 'lemma' => 'run', 'normalized_lemma' => 'run',
        'part_of_speech' => 'verb', 'type' => 'word', 'status' => 'accepted',
    ]);

    app(AiCandidateApplyService::class)->apply($run);

    expect(Lexeme::query()->where('normalized_lemma', 'run')->first()->part_of_speech)->toBe('verb');
});

test('apply backfills part_of_speech on a matched lexeme that has none yet', function () {
    $run = makeApplyRun();
    $existing = Lexeme::query()->create([
        'slug' => 'en-run', 'language' => 'en', 'lemma' => 'run', 'normalized_lemma' => 'run', 'status' => Lexeme::STATUS_PUBLISHED,
    ]);
    $run->lexemeCandidates()->create([
        'text' => 'run', 'normalized_text' => 'run', 'type' => 'word', 'status' => 'accepted',
        'matched_lexeme_id' => $existing->id, 'part_of_speech' => 'verb',
    ]);

    app(AiCandidateApplyService::class)->apply($run);

    expect($existing->fresh()->part_of_speech)->toBe('verb');
});

test('apply never overwrites an existing curated part_of_speech on a matched lexeme', function () {
    $run = makeApplyRun();
    $existing = Lexeme::query()->create([
        'slug' => 'en-run', 'language' => 'en', 'lemma' => 'run', 'normalized_lemma' => 'run',
        'status' => Lexeme::STATUS_PUBLISHED, 'part_of_speech' => 'noun',
    ]);
    $run->lexemeCandidates()->create([
        'text' => 'run', 'normalized_text' => 'run', 'type' => 'word', 'status' => 'accepted',
        'matched_lexeme_id' => $existing->id, 'part_of_speech' => 'verb',
    ]);

    app(AiCandidateApplyService::class)->apply($run);

    expect($existing->fresh()->part_of_speech)->toBe('noun');
});

test('apply creates a sense-scoped translation, example and occurrence for a candidate with a sense gloss (task 10.3)', function () {
    $run = makeApplyRun();
    $run->lexemeCandidates()->create([
        'text' => 'ran', 'normalized_text' => 'ran', 'lemma' => 'run', 'normalized_lemma' => 'run',
        'sense' => 'move quickly on foot', 'part_of_speech' => 'verb', 'type' => 'word', 'status' => 'accepted',
        'translation' => 'бежать', 'example' => 'He ran across the street.', 'example_translation' => 'Он перебежал улицу.',
    ]);

    app(AiCandidateApplyService::class)->apply($run);

    $lexeme = Lexeme::query()->where('normalized_lemma', 'run')->first();
    $sense = LexemeSense::query()->where('lexeme_id', $lexeme->id)->first();
    expect($sense)->not->toBeNull()
        ->and($sense->gloss)->toBe('move quickly on foot')
        ->and($sense->part_of_speech)->toBe('verb');

    $occurrence = contentForApplyRun($run)->lexemes()->where('text', 'ran')->first();
    expect($occurrence->lexeme_sense_id)->toBe($sense->id);

    $translation = LexemeTranslation::query()->where('lexeme_id', $lexeme->id)->first();
    expect($translation->lexeme_sense_id)->toBe($sense->id)
        ->and($translation->translation)->toBe('бежать');

    $example = LexemeExample::query()->where('lexeme_id', $lexeme->id)->first();
    expect($example->lexeme_sense_id)->toBe($sense->id);
});

test('apply groups two candidates with the same lemma and sense onto one LexemeSense', function () {
    $run1 = makeApplyRun();
    $run1->lexemeCandidates()->create([
        'text' => 'ran', 'normalized_text' => 'ran', 'lemma' => 'run', 'normalized_lemma' => 'run',
        'sense' => 'move quickly on foot', 'type' => 'word', 'status' => 'accepted',
    ]);
    app(AiCandidateApplyService::class)->apply($run1);

    $run2 = makeApplyRun();
    $run2->lexemeCandidates()->create([
        'text' => 'running', 'normalized_text' => 'running', 'lemma' => 'run', 'normalized_lemma' => 'run',
        'sense' => 'Move Quickly On Foot', 'type' => 'word', 'status' => 'accepted',
    ]);
    app(AiCandidateApplyService::class)->apply($run2);

    $lexeme = Lexeme::query()->where('normalized_lemma', 'run')->first();
    expect(LexemeSense::query()->where('lexeme_id', $lexeme->id)->count())->toBe(1);
});

test('apply keeps two different senses of the same lemma with independent primary translations/examples', function () {
    $run1 = makeApplyRun();
    $run1->lexemeCandidates()->create([
        'text' => 'run', 'normalized_text' => 'run', 'lemma' => 'run', 'normalized_lemma' => 'run',
        'sense' => 'move quickly on foot', 'type' => 'word', 'status' => 'accepted',
        'translation' => 'бежать',
    ]);
    app(AiCandidateApplyService::class)->apply($run1);

    $run2 = makeApplyRun();
    $run2->lexemeCandidates()->create([
        'text' => 'run', 'normalized_text' => 'run', 'lemma' => 'run', 'normalized_lemma' => 'run',
        'sense' => 'manage or operate a business', 'type' => 'word', 'status' => 'accepted',
        'translation' => 'управлять',
    ]);
    app(AiCandidateApplyService::class)->apply($run2);

    $lexeme = Lexeme::query()->where('normalized_lemma', 'run')->first();
    expect(LexemeSense::query()->where('lexeme_id', $lexeme->id)->count())->toBe(2);

    $translations = LexemeTranslation::query()->where('lexeme_id', $lexeme->id)->where('is_primary', true)->get();
    expect($translations)->toHaveCount(2)
        ->and($translations->pluck('translation')->sort()->values()->all())->toBe(['бежать', 'управлять']);
});

test('apply keeps a candidate without a sense sense-less, not attached to any LexemeSense', function () {
    $run = makeApplyRun();
    $run->lexemeCandidates()->create([
        'text' => 'ubiquitous', 'normalized_text' => 'ubiquitous', 'type' => 'word', 'status' => 'accepted', 'translation' => 'вездесущий',
    ]);

    app(AiCandidateApplyService::class)->apply($run);

    $lexeme = Lexeme::query()->where('normalized_lemma', 'ubiquitous')->first();
    expect(LexemeSense::query()->where('lexeme_id', $lexeme->id)->count())->toBe(0);
    expect(contentForApplyRun($run)->lexemes()->where('text', 'ubiquitous')->first()->lexeme_sense_id)->toBeNull();
    expect(LexemeTranslation::query()->where('lexeme_id', $lexeme->id)->first()->lexeme_sense_id)->toBeNull();
});

test('re-applying the same run does not duplicate canonical rows', function () {
    $run = makeApplyRun();
    $run->lexemeCandidates()->create(['text' => 'run', 'normalized_text' => 'run', 'type' => 'word', 'status' => 'accepted']);

    $service = app(AiCandidateApplyService::class);
    $first = $service->apply($run);
    $second = $service->apply($run);

    expect($first)->toBe(['lexemes' => 1, 'grammar' => 0]);
    expect($second)->toBe(['lexemes' => 0, 'grammar' => 0]);
    expect(Lexeme::query()->where('normalized_lemma', 'run')->count())->toBe(1);
});

test('applying candidates rolls back a new lexeme when sense resolution fails', function () {
    $run = makeApplyRun();
    $candidate = $run->lexemeCandidates()->create([
        'text' => 'rollbackword', 'normalized_text' => 'rollbackword',
        'type' => 'word', 'status' => 'accepted',
    ]);
    $this->mock(\App\Modules\Content\Application\LexemeSenseSyncService::class)
        ->shouldReceive('sync')->once()->andThrow(new RuntimeException('Simulated sense failure'));

    expect(fn () => app(AiCandidateApplyService::class)->apply($run))
        ->toThrow(RuntimeException::class, 'Simulated sense failure');

    expect($candidate->fresh()->status)->toBe('accepted')
        ->and(Lexeme::query()->where('normalized_lemma', 'rollbackword')->exists())->toBeFalse()
        ->and(contentForApplyRun($run)->lexemes()->count())->toBe(0);
});
