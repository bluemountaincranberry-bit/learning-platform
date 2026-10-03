<?php

use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Domain\Models\GrammarTopic;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeGrammarRuleForExercisesApi(): GrammarRule
{
    $topic = GrammarTopic::query()->create(['slug' => 'topic-exapi-'.uniqid(), 'language' => 'en', 'name' => 'Topic', 'status' => 'active']);

    return GrammarRule::query()->create([
        'topic_id' => $topic->id,
        'slug' => 'rule-exapi-'.uniqid(),
        'language' => 'en',
        'title' => 'Present Perfect',
        'status' => GrammarRule::STATUS_PUBLISHED,
    ]);
}

test('exercises endpoint returns only published exercises', function () {
    $rule = makeGrammarRuleForExercisesApi();
    $rule->exercises()->create(['type' => 'cloze', 'prompt' => 'Published one', 'answer' => 'x', 'status' => 'published']);
    $rule->exercises()->create(['type' => 'cloze', 'prompt' => 'Draft one', 'answer' => 'y', 'status' => 'draft']);

    $response = $this->getJson("/api/grammar-rules/{$rule->id}/exercises");

    $response->assertOk();
    $prompts = collect($response->json('exercises'))->pluck('prompt');
    expect($prompts)->toContain('Published one')->not->toContain('Draft one');
});

test('exercises endpoint returns multiple_choice options and answer_index', function () {
    $rule = makeGrammarRuleForExercisesApi();
    $rule->exercises()->create([
        'type' => 'multiple_choice', 'prompt' => 'Pick one', 'options' => ['a', 'b', 'c'], 'answer_index' => 2, 'status' => 'published',
    ]);

    $response = $this->getJson("/api/grammar-rules/{$rule->id}/exercises");

    $response->assertOk();
    $exercise = collect($response->json('exercises'))->firstWhere('prompt', 'Pick one');
    expect($exercise['options'])->toBe(['a', 'b', 'c'])
        ->and($exercise['answer_index'])->toBe(2);
});

test('exercises endpoint returns an empty array, not an error, when there are none published', function () {
    $rule = makeGrammarRuleForExercisesApi();

    $response = $this->getJson("/api/grammar-rules/{$rule->id}/exercises");

    $response->assertOk()->assertJson(['exercises' => []]);
});

test('exercises endpoint 404s for a non-published rule', function () {
    $rule = makeGrammarRuleForExercisesApi();
    $rule->update(['status' => GrammarRule::STATUS_DRAFT]);

    $this->getJson("/api/grammar-rules/{$rule->id}/exercises")->assertNotFound();
});
