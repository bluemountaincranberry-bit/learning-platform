<?php

use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Domain\Models\GrammarTopic;

function makeGrammarRuleWithStatus(string $status, array $overrides = []): GrammarRule
{
    static $counter = 0;
    $counter++;

    $topic = GrammarTopic::query()->create([
        'slug' => "topic-{$counter}", 'language' => 'en', 'name' => "Topic {$counter}", 'status' => 'active',
    ]);

    return GrammarRule::query()->create(array_merge([
        'topic_id' => $topic->id,
        'slug' => "rule-{$counter}",
        'language' => 'en',
        'title' => "Rule {$counter}",
        'status' => $status,
        'body' => "## Rule\nBody {$counter}",
    ], $overrides));
}

test('index only returns published rules', function () {
    makeGrammarRuleWithStatus(GrammarRule::STATUS_PUBLISHED, ['title' => 'Published Rule']);
    makeGrammarRuleWithStatus(GrammarRule::STATUS_DRAFT, ['title' => 'Draft Rule']);
    makeGrammarRuleWithStatus(GrammarRule::STATUS_REVIEW, ['title' => 'Review Rule']);
    makeGrammarRuleWithStatus(GrammarRule::STATUS_ARCHIVED, ['title' => 'Archived Rule']);

    $response = $this->getJson('/api/grammar-rules')->assertOk();

    $titles = collect($response->json('data'))->pluck('title');
    expect($titles)->toContain('Published Rule')
        ->and($titles)->not->toContain('Draft Rule', 'Review Rule', 'Archived Rule');
});

test('index filters by language and level', function () {
    makeGrammarRuleWithStatus(GrammarRule::STATUS_PUBLISHED, ['title' => 'EN B1', 'language' => 'en', 'level' => 'B1']);
    makeGrammarRuleWithStatus(GrammarRule::STATUS_PUBLISHED, ['title' => 'ES B1', 'language' => 'es', 'level' => 'B1']);
    makeGrammarRuleWithStatus(GrammarRule::STATUS_PUBLISHED, ['title' => 'EN A1', 'language' => 'en', 'level' => 'A1']);

    $response = $this->getJson('/api/grammar-rules?language=en&level=B1')->assertOk();

    $titles = collect($response->json('data'))->pluck('title')->all();
    expect($titles)->toBe(['EN B1']);
});

test('response includes body and topic but not admin-only fields', function () {
    $rule = makeGrammarRuleWithStatus(GrammarRule::STATUS_PUBLISHED, ['title' => 'Rule With Body']);

    $response = $this->getJson('/api/grammar-rules')->assertOk();
    $item = collect($response->json('data'))->firstWhere('title', 'Rule With Body');

    expect($item)->toHaveKeys(['id', 'slug', 'title', 'language', 'level', 'summary', 'body', 'topic'])
        ->and($item)->not->toHaveKey('coverage_state')
        ->and($item['body'])->toContain('Body');
});

test('show returns a published rule with examples', function () {
    $rule = makeGrammarRuleWithStatus(GrammarRule::STATUS_PUBLISHED, ['title' => 'Detail Rule']);
    $rule->examples()->create(['language' => 'en', 'example' => 'I have been here.', 'translation' => 'Я был здесь.', 'is_primary' => true, 'sort_order' => 0]);

    $response = $this->getJson("/api/grammar-rules/{$rule->id}")->assertOk();

    expect($response->json('rule.title'))->toBe('Detail Rule')
        ->and($response->json('rule.examples.0.example'))->toBe('I have been here.')
        ->and($response->json('rule.examples.0.translation'))->toBe('Я был здесь.');
});

test('show returns 404 for a non-published rule', function () {
    $rule = makeGrammarRuleWithStatus(GrammarRule::STATUS_DRAFT);

    $this->getJson("/api/grammar-rules/{$rule->id}")->assertNotFound();
});
