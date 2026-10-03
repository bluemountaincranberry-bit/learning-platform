<?php

use App\Modules\Infrastructure\Domain\Models\EntityRevision;
use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Domain\Models\GrammarTopic;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeGrammarRuleForRevisions(): GrammarRule
{
    $topic = GrammarTopic::query()->create(['slug' => 'topic-x', 'language' => 'en', 'name' => 'Topic X', 'status' => 'active']);

    return GrammarRule::query()->create([
        'topic_id' => $topic->id, 'slug' => 'rule-x', 'language' => 'en', 'title' => 'Present Simple', 'status' => GrammarRule::STATUS_DRAFT,
    ]);
}

test('creating a revisionable model records a revision with old null and new values', function () {
    $rule = makeGrammarRuleForRevisions();

    expect($rule->revisions()->count())->toBe(1);
    $revision = $rule->revisions()->first();
    expect($revision->changes['title']['old'])->toBeNull()
        ->and($revision->changes['title']['new'])->toBe('Present Simple')
        ->and($revision->source)->toBe(EntityRevision::SOURCE_ADMIN);
});

test('updating a revisionable field records old and new values', function () {
    $rule = makeGrammarRuleForRevisions();
    $rule->update(['title' => 'Present Simple Tense']);

    $revision = $rule->revisions()->first();
    expect($revision->changes)->toHaveKey('title')
        ->and($revision->changes['title']['old'])->toBe('Present Simple')
        ->and($revision->changes['title']['new'])->toBe('Present Simple Tense');
});

test('updating a non-revisionable field does not record a revision', function () {
    $rule = makeGrammarRuleForRevisions();
    $rule->update(['sort_order' => 99]);

    expect($rule->revisions()->count())->toBe(1); // only the creation revision
});

test('saving with no actual changes to revisionable fields does not create an extra revision', function () {
    $rule = makeGrammarRuleForRevisions();
    $rule->update(['title' => 'Present Simple']); // same value

    expect($rule->revisions()->count())->toBe(1);
});

test('revision records the acting user as causer', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $rule = makeGrammarRuleForRevisions();
    $revision = $rule->revisions()->first();

    expect($revision->causer_id)->toBe($user->id)
        ->and($revision->causer_type)->toBe(User::class);
});

test('multiple field changes in one save produce one revision with all changed fields', function () {
    $rule = makeGrammarRuleForRevisions();
    $rule->update(['title' => 'New Title', 'summary' => 'New summary']);

    $revision = $rule->revisions()->first();
    expect($revision->changes)->toHaveKeys(['title', 'summary']);
});
