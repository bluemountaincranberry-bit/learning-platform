<?php

use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Domain\Models\GrammarRuleExample;
use App\Modules\Content\Domain\Models\GrammarTopic;
use App\Modules\Learning\Domain\Models\Lesson;
use App\Modules\Learning\Domain\Models\LessonGrammarCandidate;
use App\Modules\Learning\Domain\Models\UserGrammarRule;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a lesson grammar point can be saved as a private personal rule with its source and examples', function () {
    $owner = User::factory()->create();
    test()->actingAs($owner);
    $lesson = Lesson::query()->create([
        'user_id' => $owner->id,
        'status' => Lesson::STATUS_ACTIVE,
        'title' => 'Tuesday class',
        'language' => 'en',
    ]);
    $candidate = LessonGrammarCandidate::query()->create([
        'lesson_id' => $lesson->id,
        'title' => 'Past habits with used to',
        'summary' => 'Use used to for repeated past actions.',
        'body' => 'Use the infinitive after used to.',
        'example' => 'I used to walk to school.',
        'example_translation' => 'Раньше я ходила в школу пешком.',
        'status' => LessonGrammarCandidate::STATUS_NEW,
        'source' => 'manual',
    ]);

    $response = test()->postJson("/api/lessons/{$lesson->id}/grammar/{$candidate->id}/add-to-my-grammar")
        ->assertOk()
        ->assertJsonPath('in_my_grammar', true)
        ->assertJsonPath('is_personal', true)
        ->assertJsonPath('status', LessonGrammarCandidate::STATUS_LINKED);

    $ruleId = $response->json('grammar_rule_id');
    $rule = GrammarRule::query()->findOrFail($ruleId);

    expect($rule->owner_user_id)->toBe($owner->id)
        ->and($rule->source_lesson_id)->toBe($lesson->id)
        ->and($rule->title)->toBe('Past habits with used to')
        ->and($rule->summary)->toBe('Use used to for repeated past actions.')
        ->and($rule->body)->toBe('Use the infinitive after used to.')
        ->and($rule->examples()->first()->example)->toBe('I used to walk to school.')
        ->and($rule->examples()->first()->translation)->toBe('Раньше я ходила в школу пешком.')
        ->and(UserGrammarRule::query()->where('user_id', $owner->id)->where('grammar_rule_id', $ruleId)->count())->toBe(1);

    test()->getJson("/api/grammar-rules/{$ruleId}")
        ->assertOk()
        ->assertJsonPath('rule.is_personal', true)
        ->assertJsonPath('rule.source_lesson.id', $lesson->id)
        ->assertJsonPath('rule.examples.0.example', 'I used to walk to school.')
        ->assertJsonPath('rule.in_my_list', true);
    test()->getJson('/api/me/grammar-rules')
        ->assertOk()
        ->assertJsonPath('data.0.grammar_rule_id', $ruleId);

    test()->postJson("/api/lessons/{$lesson->id}/grammar/{$candidate->id}/add-to-my-grammar")
        ->assertOk()
        ->assertJsonPath('grammar_rule_id', $ruleId);

    expect(GrammarRule::query()->where('owner_user_id', $owner->id)->count())->toBe(1)
        ->and(UserGrammarRule::query()->where('user_id', $owner->id)->where('grammar_rule_id', $ruleId)->count())->toBe(1);
});

test('a lesson grammar point with an exact published catalog match reuses that rule', function () {
    $owner = User::factory()->create();
    test()->actingAs($owner);
    $lesson = Lesson::query()->create(['user_id' => $owner->id, 'status' => Lesson::STATUS_ACTIVE, 'language' => 'en']);
    $topic = GrammarTopic::query()->create(['slug' => 'conditionals', 'language' => 'en', 'name' => 'Conditionals', 'status' => 'active']);
    $catalogRule = GrammarRule::query()->create([
        'topic_id' => $topic->id,
        'slug' => 'first-conditional',
        'language' => 'en',
        'title' => 'First Conditional',
        'status' => GrammarRule::STATUS_PUBLISHED,
    ]);
    $candidate = LessonGrammarCandidate::query()->create([
        'lesson_id' => $lesson->id,
        'title' => ' first  conditional. ',
        'summary' => 'If + present, will + verb.',
        'status' => LessonGrammarCandidate::STATUS_NEW,
    ]);

    test()->postJson("/api/lessons/{$lesson->id}/grammar/{$candidate->id}/add-to-my-grammar")
        ->assertOk()
        ->assertJsonPath('grammar_rule_id', $catalogRule->id)
        ->assertJsonPath('is_personal', false);

    expect(GrammarRule::query()->where('owner_user_id', $owner->id)->exists())->toBeFalse()
        ->and(UserGrammarRule::query()->where('user_id', $owner->id)->where('grammar_rule_id', $catalogRule->id)->exists())->toBeTrue();
});

test('another learner cannot open a personal grammar rule', function () {
    $owner = User::factory()->create();
    $lesson = Lesson::query()->create(['user_id' => $owner->id, 'status' => Lesson::STATUS_ACTIVE, 'language' => 'en']);
    $candidate = LessonGrammarCandidate::query()->create([
        'lesson_id' => $lesson->id,
        'title' => 'Past habits',
        'status' => LessonGrammarCandidate::STATUS_NEW,
    ]);

    test()->actingAs($owner)
        ->postJson("/api/lessons/{$lesson->id}/grammar/{$candidate->id}/add-to-my-grammar")
        ->assertOk();
    $ruleId = $candidate->fresh()->personal_grammar_rule_id;

    test()->actingAs(User::factory()->create())
        ->getJson("/api/grammar-rules/{$ruleId}")
        ->assertNotFound();
    test()->getJson("/api/grammar-rules/{$ruleId}/examples")
        ->assertNotFound();
});
