<?php

use App\Contracts\Ai\AiFieldEditCapability;
use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Domain\Models\GrammarRuleExampleHide;
use App\Modules\Content\Domain\Models\GrammarTopic;
use App\Modules\Infrastructure\Domain\Models\EntityRevision;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Model;

function grammarRuleForEditor(): GrammarRule
{
    $topic = GrammarTopic::query()->create([
        'slug' => 'editor-'.uniqid(), 'language' => 'en', 'name' => 'Editor test', 'status' => 'active',
    ]);

    $rule = GrammarRule::query()->create([
        'topic_id' => $topic->id, 'slug' => 'editor-rule-'.uniqid(), 'language' => 'en',
        'title' => 'Present simple', 'status' => GrammarRule::STATUS_PUBLISHED,
        'summary' => 'A short summary.', 'body' => '## Use\nTalk about habits.',
    ]);
    $rule->examples()->create([
        'language' => 'en', 'example' => 'She walks to work.', 'translation' => 'Она ходит на работу.',
        'is_primary' => true, 'sort_order' => 10,
    ]);

    return $rule->fresh('examples');
}

function bindGrammarRuleEditorProposal(array $proposal): void
{
    app()->instance(AiFieldEditCapability::class, new class($proposal) implements AiFieldEditCapability
    {
        public function __construct(private array $proposal) {}

        public function proposeConversation(Model $subject, \App\Contracts\Ai\AiConversationalEditablePrompt $builder, string $instruction, array $conversation, array $draft): array
        {
            return $this->proposal;
        }
    });
}

function grammarRuleEditorDraft(string $title, string $example): array
{
    return [
        'title' => $title,
        'summary' => 'Use it for habits and routines.',
        'body' => '## Form\nSubject + verb.\n\n## Use\nDescribe routines.',
        'examples' => [[
            'language' => 'en', 'example' => $example, 'translation' => 'Она читает каждый день.',
            'is_primary' => true, 'sort_order' => 10,
        ]],
    ];
}

test('authenticated learner can request an AI draft without changing the shared rule', function () {
    config(['ai.enabled' => true]);
    $rule = grammarRuleForEditor();
    bindGrammarRuleEditorProposal(grammarRuleEditorDraft('Present simple for routines', 'She reads every day.'));

    $response = $this->actingAs(User::factory()->create())->postJson("/api/grammar-rules/{$rule->id}/editor/proposals", [
        'instruction' => 'Make it clearer and add a natural example.',
        'draft' => [
            'title' => $rule->title, 'summary' => $rule->summary, 'body' => $rule->body,
            'examples' => $rule->examples->map(fn ($example) => $example->only(['language', 'example', 'translation', 'is_primary', 'sort_order']))->all(),
        ],
        'conversation' => [],
    ])->assertOk()->assertJsonPath('proposal.title', 'Present simple for routines');

    expect($rule->fresh()->title)->toBe('Present simple')
        ->and($rule->fresh()->examples()->first()->example)->toBe('She walks to work.');
});

test('applying a reviewed draft saves its text and examples and stores a restorable snapshot', function () {
    $rule = grammarRuleForEditor();
    $user = User::factory()->create();
    $originalExample = $rule->examples()->firstOrFail();
    GrammarRuleExampleHide::query()->create(['user_id' => $user->id, 'grammar_rule_example_id' => $originalExample->id]);
    $oldVersion = (int) $rule->editor_version;
    $draft = grammarRuleEditorDraft('Present simple for routines', 'She reads every day.');

    $apply = $this->actingAs($user)->putJson("/api/grammar-rules/{$rule->id}/editor", [
        ...$draft, 'expected_version' => $oldVersion,
    ])->assertOk()->assertJsonPath('rule.title', $draft['title']);

    $updatedVersion = $apply->json('rule.editor_version');
    expect($updatedVersion)->toBeGreaterThan($oldVersion)
        ->and($rule->fresh()->examples()->first()->example)->toBe('She reads every day.')
        ->and($originalExample->fresh()->archived_at)->not->toBeNull()
        ->and(GrammarRuleExampleHide::query()->where('grammar_rule_example_id', $originalExample->id)->exists())->toBeTrue();

    $revision = EntityRevision::query()
        ->where('revisionable_type', $rule->getMorphClass())
        ->where('revisionable_id', $rule->id)
        ->whereNotNull('changes->editor_snapshot')
        ->latest('id')
        ->firstOrFail();

    $this->actingAs($user)->getJson("/api/grammar-rules/{$rule->id}/editor/revisions")
        ->assertOk()->assertJsonPath('revisions.0.id', $revision->id);

    $this->actingAs($user)->postJson("/api/grammar-rules/{$rule->id}/editor/revisions/{$revision->id}/restore", [
        'expected_version' => $updatedVersion,
    ])->assertOk()->assertJsonPath('rule.title', 'Present simple');

    expect($rule->fresh()->examples()->first()->example)->toBe('She walks to work.')
        ->and($originalExample->fresh()->archived_at)->toBeNull()
        ->and(GrammarRuleExampleHide::query()->where('grammar_rule_example_id', $originalExample->id)->exists())->toBeTrue();
});

test('editor rejects stale drafts and unauthenticated writes', function () {
    $rule = grammarRuleForEditor();
    $version = (int) $rule->editor_version;
    $user = User::factory()->create();
    $draft = grammarRuleEditorDraft('Present simple', 'She reads every day.');

    $this->actingAs($user)->putJson("/api/grammar-rules/{$rule->id}/editor", [...$draft, 'expected_version' => $version])->assertOk();
    $this->actingAs($user)->putJson("/api/grammar-rules/{$rule->id}/editor", [...$draft, 'expected_version' => $version])->assertStatus(409);
});

test('unauthenticated editor requests are denied', function () {
    $rule = grammarRuleForEditor();
    $draft = grammarRuleEditorDraft('Present simple', 'She reads every day.');

    $this->postJson("/api/grammar-rules/{$rule->id}/editor/proposals", [
        'instruction' => 'Rewrite this.', 'draft' => $draft, 'conversation' => [],
    ])->assertUnauthorized();
});

test('editor only exposes published shared grammar rules', function () {
    $user = User::factory()->create();
    $draft = grammarRuleForEditor();
    $draft->update(['status' => GrammarRule::STATUS_DRAFT]);

    $this->actingAs($user)->getJson("/api/grammar-rules/{$draft->id}/editor")->assertNotFound();
});

test('incomplete AI proposals and disabled AI leave the catalog untouched', function () {
    $rule = grammarRuleForEditor();
    $user = User::factory()->create();
    $draft = grammarRuleEditorDraft($rule->title, 'She walks to work.');
    bindGrammarRuleEditorProposal(['title' => 'Incomplete proposal']);

    config(['ai.enabled' => true]);
    $this->actingAs($user)->postJson("/api/grammar-rules/{$rule->id}/editor/proposals", [
        'instruction' => 'Make this clearer.', 'draft' => $draft, 'conversation' => [],
    ])->assertStatus(502);

    config(['ai.enabled' => false]);
    $this->actingAs($user)->postJson("/api/grammar-rules/{$rule->id}/editor/proposals", [
        'instruction' => 'Make this clearer.', 'draft' => $draft, 'conversation' => [],
    ])->assertStatus(503);

    expect($rule->fresh()->title)->toBe('Present simple');
});
