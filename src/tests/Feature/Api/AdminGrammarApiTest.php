<?php

use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Domain\Models\GrammarTopic;
use App\Modules\Content\Domain\Models\Lexeme;
use App\Modules\User\Models\User;
use Database\Seeders\GrammarCatalogSeeder;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'editor', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
});

function makeEditor(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::findByName('editor', 'web'));

    return $user;
}

function makeRegularUser(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::findByName('user', 'web'));

    return $user;
}

test('admin grammar api requires auth and manage-content permission', function () {
    $this->getJson('/api/admin/grammar/topics')->assertUnauthorized();

    $this->actingAs(makeRegularUser())
        ->getJson('/api/admin/grammar/topics')
        ->assertForbidden();

    $this->actingAs(makeEditor())
        ->getJson('/api/admin/grammar/topics')
        ->assertOk();
});

test('editor can create update and delete grammar topic', function () {
    $editor = makeEditor();

    $create = $this->actingAs($editor)->postJson('/api/admin/grammar/topics', [
        'name' => 'Question forms',
        'status' => GrammarTopic::STATUS_ACTIVE,
        'sort_order' => 40,
    ])->assertCreated();

    $topicId = $create->json('topic.id');

    expect(GrammarTopic::query()->whereKey($topicId)->exists())->toBeTrue();

    $this->actingAs($editor)->patchJson("/api/admin/grammar/topics/{$topicId}", [
        'description' => 'Interrogative structures for guided practice.',
        'slug' => 'question-forms',
    ])->assertOk()
        ->assertJsonPath('topic.slug', 'question-forms')
        ->assertJsonPath('topic.description', 'Interrogative structures for guided practice.');

    $this->actingAs($editor)->deleteJson("/api/admin/grammar/topics/{$topicId}")
        ->assertNoContent();

    expect(GrammarTopic::query()->whereKey($topicId)->exists())->toBeFalse();
});

test('rules index supports coverage and linked content filters', function () {
    $editor = makeEditor();
    $this->seed(GrammarCatalogSeeder::class);

    $content = Content::query()->create([
        'type' => 'grammar',
        'title' => 'Articles Drill',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
    ]);

    $rule = GrammarRule::query()->where('slug', 'basic-articles-a-an-the')->firstOrFail();
    $rule->contentLinks()->create([
        'content_id' => $content->id,
        'status' => 'linked',
    ]);

    $this->actingAs($editor)
        ->getJson("/api/admin/grammar/rules?coverage_state=covered&linked_content=true&content_id={$content->id}&q=articles")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'basic-articles-a-an-the')
        ->assertJsonPath('data.0.coverage_state', 'covered');

    $this->actingAs($editor)
        ->getJson('/api/admin/grammar/topics?coverage_state=covered')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'articles-and-determiners');
});

test('editor can create and update grammar rule with lexemes and examples', function () {
    $editor = makeEditor();

    $topic = GrammarTopic::query()->create([
        'slug' => 'adverbs',
        'language' => 'en',
        'name' => 'Adverbs',
        'status' => GrammarTopic::STATUS_ACTIVE,
    ]);

    $lexemeA = Lexeme::query()->create([
        'slug' => 'always-adverb',
        'language' => 'en',
        'lemma' => 'always',
        'normalized_lemma' => 'always',
        'part_of_speech' => 'adverb',
        'status' => Lexeme::STATUS_PUBLISHED,
    ]);
    $lexemeB = Lexeme::query()->create([
        'slug' => 'never-adverb',
        'language' => 'en',
        'lemma' => 'never',
        'normalized_lemma' => 'never',
        'part_of_speech' => 'adverb',
        'status' => Lexeme::STATUS_PUBLISHED,
    ]);

    $create = $this->actingAs($editor)->postJson('/api/admin/grammar/rules', [
        'topic_id' => $topic->id,
        'title' => 'Adverbs of frequency',
        'status' => GrammarRule::STATUS_REVIEW,
        'level' => 'A2',
        'summary' => 'Place common frequency adverbs in simple statements.',
        'lexeme_ids' => [$lexemeA->id, $lexemeB->id],
        'examples' => [
            ['example' => 'She always arrives early.', 'is_primary' => true],
        ],
    ])->assertCreated();

    $ruleId = $create->json('rule.id');

    expect(GrammarRule::query()->whereKey($ruleId)->exists())->toBeTrue();
    expect(GrammarRule::query()->findOrFail($ruleId)->lexemes()->count())->toBe(2);

    $this->actingAs($editor)->patchJson("/api/admin/grammar/rules/{$ruleId}", [
        'title' => 'Adverbs of frequency in routines',
        'status' => GrammarRule::STATUS_PUBLISHED,
        'examples' => [
            ['example' => 'I never drink coffee at night.', 'is_primary' => true],
        ],
        'lexeme_ids' => [$lexemeB->id],
    ])->assertOk()
        ->assertJsonPath('rule.title', 'Adverbs of frequency in routines')
        ->assertJsonPath('rule.status', GrammarRule::STATUS_PUBLISHED)
        ->assertJsonPath('rule.lexemes_count', 1)
        ->assertJsonPath('rule.examples_count', 1);

    $this->actingAs($editor)->getJson("/api/admin/grammar/rules/{$ruleId}")
        ->assertOk()
        ->assertJsonPath('rule.lexemes.0.slug', 'never-adverb')
        ->assertJsonPath('rule.coverage_state', 'needs_content');
});

test('editor can create and update lexeme with rules examples and associations', function () {
    $editor = makeEditor();

    $topic = GrammarTopic::query()->create([
        'slug' => 'modals',
        'language' => 'en',
        'name' => 'Modals',
        'status' => GrammarTopic::STATUS_ACTIVE,
    ]);

    $rule = GrammarRule::query()->create([
        'topic_id' => $topic->id,
        'slug' => 'must-for-obligation',
        'language' => 'en',
        'title' => 'Must for obligation',
        'status' => GrammarRule::STATUS_PUBLISHED,
    ]);

    $related = Lexeme::query()->create([
        'slug' => 'should-modal',
        'language' => 'en',
        'lemma' => 'should',
        'normalized_lemma' => 'should',
        'part_of_speech' => 'modal',
        'status' => Lexeme::STATUS_PUBLISHED,
    ]);

    $create = $this->actingAs($editor)->postJson('/api/admin/grammar/lexemes', [
        'lemma' => 'must',
        'part_of_speech' => 'modal',
        'status' => Lexeme::STATUS_REVIEW,
        'rule_ids' => [$rule->id],
        'examples' => [
            ['example' => 'You must wear a seatbelt.', 'is_primary' => true],
        ],
        'associations' => [
            ['related_lexeme_id' => $related->id, 'type' => 'related'],
        ],
    ])->assertCreated();

    $lexemeId = $create->json('lexeme.id');

    $this->actingAs($editor)->patchJson("/api/admin/grammar/lexemes/{$lexemeId}", [
        'normalized_lemma' => 'must',
        'status' => Lexeme::STATUS_PUBLISHED,
        'notes' => 'High-priority obligation modal.',
        'associations' => [],
    ])->assertOk()
        ->assertJsonPath('lexeme.status', Lexeme::STATUS_PUBLISHED)
        ->assertJsonPath('lexeme.notes', 'High-priority obligation modal.')
        ->assertJsonPath('lexeme.associations_count', 0)
        ->assertJsonPath('lexeme.coverage_state', 'needs_content');

    $this->actingAs($editor)->getJson("/api/admin/grammar/lexemes/{$lexemeId}")
        ->assertOk()
        ->assertJsonPath('lexeme.rules.0.slug', 'must-for-obligation')
        ->assertJsonPath('lexeme.examples.0.example', 'You must wear a seatbelt.');
});

test('coverage endpoint returns summary counts for topics rules and lexemes', function () {
    $editor = makeEditor();
    $this->seed(GrammarCatalogSeeder::class);

    $content = Content::query()->create([
        'type' => 'grammar',
        'title' => 'Modal Practice',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
    ]);

    $rule = GrammarRule::query()->where('slug', 'can-for-ability')->firstOrFail();
    $rule->contentLinks()->create([
        'content_id' => $content->id,
        'status' => 'linked',
    ]);

    foreach ($rule->lexemes as $lexeme) {
        $lexeme->contentLinks()->create([
            'content_id' => $content->id,
            'type' => 'word',
            'text' => $lexeme->lemma,
            'sort_order' => 0,
        ]);
    }

    $this->actingAs($editor)->getJson('/api/admin/grammar/coverage')
        ->assertOk()
        ->assertJsonPath('coverage.topics.total', 3)
        ->assertJsonPath('coverage.rules.total', 3)
        ->assertJsonPath('coverage.rules.covered', 1)
        ->assertJsonPath('coverage.rules.needs_content', 2)
        ->assertJsonPath('coverage.lexemes.covered', 1);
});
