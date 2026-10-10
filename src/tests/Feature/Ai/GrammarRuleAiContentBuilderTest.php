<?php

use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Domain\Models\GrammarTopic;
use App\Modules\Content\Application\Ai\GrammarRuleAiContentBuilder;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

test('buildPrompt includes the rule title, topic and existing examples', function () {
    $topic = GrammarTopic::query()->create(['slug' => 'perfect-tenses', 'language' => 'en', 'name' => 'Perfect Tenses', 'status' => 'active']);
    $rule = GrammarRule::query()->create([
        'topic_id' => $topic->id, 'slug' => 'present-perfect', 'language' => 'en', 'title' => 'Present Perfect', 'status' => 'draft',
    ]);
    $rule->examples()->create(['language' => 'en', 'example' => 'I have visited Paris.', 'is_primary' => true, 'sort_order' => 0]);

    $prompt = app(GrammarRuleAiContentBuilder::class)->buildPrompt($rule, null);

    expect($prompt['system'])->toContain('Present Perfect')
        ->and($prompt['system'])->toContain('Perfect Tenses')
        ->and($prompt['system'])->toContain('I have visited Paris.')
        ->and($prompt['schema'])->toHaveKeys(['summary', 'body']);
});

test('buildPrompt uses the instruction as the user message when given', function () {
    $topic = GrammarTopic::query()->create(['slug' => 'topic-x', 'language' => 'en', 'name' => 'Topic X', 'status' => 'active']);
    $rule = GrammarRule::query()->create(['topic_id' => $topic->id, 'slug' => 'rule-x', 'language' => 'en', 'title' => 'Rule X', 'status' => 'draft']);

    $prompt = app(GrammarRuleAiContentBuilder::class)->buildPrompt($rule, 'Make it shorter.');

    expect($prompt['user'])->toBe('Make it shorter.');
});

test('buildConversationPrompt grounds each turn in the current draft and returns complete editable fields', function () {
    $topic = GrammarTopic::query()->create(['slug' => 'editor-topic', 'language' => 'en', 'name' => 'Editor topic', 'status' => 'active']);
    $rule = GrammarRule::query()->create([
        'topic_id' => $topic->id, 'slug' => 'editor-rule', 'language' => 'en', 'title' => 'Present Simple', 'status' => 'published',
    ]);
    $draft = [
        'title' => 'Present Simple', 'summary' => 'Habits.', 'body' => '## Use\nHabits.',
        'examples' => [['id' => 24, 'language' => 'en', 'example' => 'I walk.', 'translation' => 'Я хожу.', 'is_primary' => true]],
    ];

    $prompt = app(GrammarRuleAiContentBuilder::class)->buildConversationPrompt(
        $rule,
        'Add one more example.',
        [['role' => 'user', 'content' => 'Make the explanation easier to read.']],
        $draft,
    );

    expect($prompt['user'])->toContain('Make the explanation easier to read.')
        ->and($prompt['user'])->toContain('Add one more example.')
        ->and($prompt['user'])->toContain('"id": 24')
        ->and($prompt['schema'])->toHaveKeys(['title', 'summary', 'body', 'examples'])
        ->and($prompt['feature'])->toBe('grammar_rule_editor');
});

test('buildPrompt falls back to a generic request when no instruction is given', function () {
    $topic = GrammarTopic::query()->create(['slug' => 'topic-x', 'language' => 'en', 'name' => 'Topic X', 'status' => 'active']);
    $rule = GrammarRule::query()->create(['topic_id' => $topic->id, 'slug' => 'rule-x', 'language' => 'en', 'title' => 'Rule X', 'status' => 'draft']);

    $prompt = app(GrammarRuleAiContentBuilder::class)->buildPrompt($rule, null);

    expect($prompt['user'])->not->toBeEmpty();
});

test('structureInstructions mentions the required section headings', function () {
    $instructions = GrammarRuleAiContentBuilder::structureInstructions();

    expect($instructions)->toContain('Rule')
        ->and($instructions)->toContain('Formation')
        ->and($instructions)->toContain('Usage')
        ->and($instructions)->toContain('Common mistakes');
});
