<?php

use App\Contracts\Ai\AiJsonClient;
use App\Modules\Ai\Interfaces\Jobs\GenerateGrammarExercisesJob;
use App\Modules\Content\Domain\Models\GrammarExamAttempt;
use App\Modules\Content\Domain\Models\GrammarExerciseGeneration;
use App\Modules\Content\Domain\Models\GrammarExerciseReport;
use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Domain\Models\GrammarRuleExercise;
use App\Modules\Content\Domain\Models\GrammarTopic;
use App\Modules\Learning\Domain\Models\UserGrammarRule;
use App\Modules\Learning\Interfaces\Listeners\TopUpGrammarExercisePool;
use Illuminate\Events\CallQueuedListener;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

function practiceRule(string $status = GrammarRule::STATUS_PUBLISHED): GrammarRule
{
    $topic = GrammarTopic::query()->create(['slug' => 'pt-'.uniqid(), 'language' => 'en', 'name' => 'Tenses', 'status' => 'active']);

    return GrammarRule::query()->create([
        'topic_id' => $topic->id, 'slug' => 'present-perfect-'.uniqid(), 'language' => 'en',
        'title' => 'Present Perfect', 'summary' => 'Past action, present result.', 'body' => 'have + past participle',
        'status' => $status,
    ]);
}

/** One exercise of each of the five types, as the AI would return them. */
function fiveTypeItems(string $suffix = ''): array
{
    return [
        ['type' => 'multiple_choice', 'prompt' => "I _____ this film twice{$suffix}.", 'options' => ['saw', 'have seen', 'seen'], 'answer_index' => 1, 'hint' => 'Twice = experience up to now.', 'explanation' => 'Experience → present perfect.'],
        $suffix === ''
            ? ['type' => 'build', 'prompt' => 'Make a question', 'answer' => 'Have you ever been to Japan?', 'tiles' => ['Have', 'you', 'ever', 'been', 'to Japan?'], 'hint' => 'Start with the helper verb.']
            : ['type' => 'build', 'prompt' => 'Make a question', 'answer' => 'Has she ever been to Spain?', 'tiles' => ['Has', 'she', 'ever', 'been', 'to Spain?'], 'hint' => 'Start with the helper verb.'],
        ['type' => 'cloze', 'prompt' => "She _____ (lose) her keys{$suffix}.", 'answer' => 'has lost', 'hint' => 'Irregular verb: think of its 3rd form.', 'explanation' => 'lose – lost – lost.'],
        ['type' => 'transform', 'instruction' => 'Make it a question', 'prompt' => "They have finished{$suffix}.", 'answer' => 'Have they finished?', 'hint' => 'Move the helper verb.'],
        ['type' => 'fix', 'prompt' => "I have seen him yesterday{$suffix}.", 'answer' => 'I saw him yesterday.', 'hint' => '"Yesterday" is a finished time.'],
    ];
}

function addExercises(GrammarRule $rule, array $items, array $overrides = []): void
{
    foreach ($items as $item) {
        $rule->exercises()->create(array_merge([
            'type' => $item['type'], 'prompt' => $item['prompt'], 'instruction' => $item['instruction'] ?? null,
            'answer' => $item['answer'] ?? null, 'options' => $item['options'] ?? null, 'answer_index' => $item['answer_index'] ?? null,
            'tiles' => $item['tiles'] ?? null, 'accepted_answers' => $item['accepted_answers'] ?? null,
            'hint' => $item['hint'] ?? null, 'explanation' => $item['explanation'] ?? null,
            'status' => GrammarRuleExercise::STATUS_DRAFT, 'origin' => GrammarRuleExercise::ORIGIN_AI,
        ], $overrides));
    }
}

function learner(): User
{
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    return $user;
}

function exerciseOf(GrammarRule $rule, string $type): GrammarRuleExercise
{
    return $rule->exercises()->where('type', $type)->firstOrFail();
}

beforeEach(function () {
    config(['ai.enabled' => true]);
    // Never reach a real AI provider: generation jobs are asserted, not run.
    Queue::fake();
});

test('a rule with no exercises gets them in one tap and the round starts', function () {
    $rule = practiceRule();
    learner();

    $this->postJson("/api/grammar-rules/{$rule->id}/practice/rounds", ['level' => 'medium', 'count' => 10])
        ->assertStatus(202)
        ->assertJson(['status' => 'preparing']);

    Queue::assertPushed(GenerateGrammarExercisesJob::class);
    $generation = GrammarExerciseGeneration::query()->sole();
    expect($generation->size)->toBe(15);

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andReturn(['exercises' => [...fiveTypeItems(), ...fiveTypeItems(' again')]]);
    app()->instance(AiJsonClient::class, $client);
    app()->call([new GenerateGrammarExercisesJob($generation->id), 'handle']);

    expect($generation->fresh()->status)->toBe(GrammarExerciseGeneration::STATUS_DONE);

    $round = $this->postJson("/api/grammar-rules/{$rule->id}/practice/rounds", ['level' => 'medium', 'count' => 10])
        ->assertOk()
        ->assertJson(['status' => 'ready'])
        ->json('exercises');

    expect($round)->toHaveCount(10)
        ->and(array_column($round, 'type'))->toBe(['multiple_choice', 'multiple_choice', 'build', 'build', 'cloze', 'cloze', 'transform', 'transform', 'fix', 'fix'])
        ->and($round[0])->not->toHaveKeys(['answer', 'answer_index', 'hint', 'explanation', 'accepted_answers'])
        ->and($round[0]['origin'])->toBe('ai');
});

test('the round starts with what exists once at least five are ready', function () {
    $rule = practiceRule();
    addExercises($rule, fiveTypeItems());
    learner();

    $this->postJson("/api/grammar-rules/{$rule->id}/practice/rounds", ['level' => 'medium', 'count' => 10])
        ->assertOk()
        ->assertJsonCount(5, 'exercises');

    // The short pool still queued a top-up batch.
    Queue::assertPushed(GenerateGrammarExercisesJob::class);
});

test('generation is one batch per rule at a time and three per learner per day', function () {
    $rule = practiceRule();
    learner();

    $this->postJson("/api/grammar-rules/{$rule->id}/practice/rounds", ['level' => 'medium', 'count' => 10])->assertStatus(202);
    $this->postJson("/api/grammar-rules/{$rule->id}/practice/rounds", ['level' => 'medium', 'count' => 10])->assertStatus(202);
    expect(GrammarExerciseGeneration::query()->count())->toBe(1);

    GrammarExerciseGeneration::query()->update(['status' => GrammarExerciseGeneration::STATUS_FAILED]);
    $this->postJson("/api/grammar-rules/{$rule->id}/practice/rounds", ['level' => 'medium', 'count' => 10])->assertStatus(202);
    GrammarExerciseGeneration::query()->update(['status' => GrammarExerciseGeneration::STATUS_FAILED]);
    $this->postJson("/api/grammar-rules/{$rule->id}/practice/rounds", ['level' => 'medium', 'count' => 10])->assertStatus(202);
    GrammarExerciseGeneration::query()->update(['status' => GrammarExerciseGeneration::STATUS_FAILED]);

    $this->postJson("/api/grammar-rules/{$rule->id}/practice/rounds", ['level' => 'medium', 'count' => 10])
        ->assertStatus(503)
        ->assertJson(['status' => 'unavailable', 'reason' => 'limited']);
    expect(GrammarExerciseGeneration::query()->count())->toBe(3);
});

test('with AI down existing exercises still practice and an empty rule says it cannot prepare', function () {
    config(['ai.enabled' => false]);
    $rule = practiceRule();
    addExercises($rule, [fiveTypeItems()[2]]);
    $empty = practiceRule();
    learner();

    $this->postJson("/api/grammar-rules/{$rule->id}/practice/rounds", ['level' => 'hard', 'count' => 5])
        ->assertOk()
        ->assertJsonCount(1, 'exercises');

    $this->postJson("/api/grammar-rules/{$empty->id}/practice/rounds", ['level' => 'medium', 'count' => 10])
        ->assertStatus(503)
        ->assertJson(['status' => 'unavailable', 'reason' => 'unavailable']);

    Queue::assertNothingPushed();
});

test('all five types check correctly', function (string $type, string $right, string $wrong) {
    $rule = practiceRule();
    addExercises($rule, fiveTypeItems());
    learner();
    $exercise = exerciseOf($rule, $type);

    $this->postJson("/api/grammar-exercises/{$exercise->id}/check", ['given' => $wrong])
        ->assertOk()->assertJson(['correct' => false]);

    // A new round starts the exercise afresh.
    $this->postJson("/api/grammar-rules/{$rule->id}/practice/rounds", ['level' => 'medium', 'count' => 5])->assertOk();
    $this->postJson("/api/grammar-exercises/{$exercise->id}/check", ['given' => $right])
        ->assertOk()->assertJson(['correct' => true, 'outcome' => 'first_try']);
})->with([
    'choose the form' => ['multiple_choice', '1', '0'],
    'build the sentence' => ['build', 'Have you ever been to Japan?', 'You have ever been to Japan?'],
    'fill the gap' => ['cloze', 'Has lost', 'has losed'],
    'transform' => ['transform', 'have they finished', 'They have finished?'],
    'fix the mistake' => ['fix', 'I saw him yesterday', 'I have seen him yesterday.'],
]);

test('first wrong gives a hint without the answer, second wrong gives the answer', function () {
    $rule = practiceRule();
    addExercises($rule, fiveTypeItems());
    learner();
    $choice = exerciseOf($rule, 'multiple_choice');
    $cloze = exerciseOf($rule, 'cloze');

    $first = $this->postJson("/api/grammar-exercises/{$choice->id}/check", ['given' => '0'])
        ->assertOk()
        ->assertJson(['correct' => false, 'hint' => 'Twice = experience up to now.', 'struck_option_index' => 0])
        ->json();
    expect($first)->not->toHaveKeys(['answer', 'explanation']);

    $this->postJson("/api/grammar-exercises/{$choice->id}/check", ['given' => '1'])
        ->assertOk()->assertJson(['correct' => true, 'outcome' => 'after_hint']);

    $this->postJson("/api/grammar-exercises/{$cloze->id}/check", ['given' => 'has losed'])
        ->assertOk()->assertJsonMissing(['answer' => 'has lost']);
    $this->postJson("/api/grammar-exercises/{$cloze->id}/check", ['given' => 'has loosed'])
        ->assertOk()
        ->assertJson(['correct' => false, 'outcome' => 'answer_shown', 'answer' => 'has lost', 'explanation' => 'lose – lost – lost.']);

    // Asking again about a settled exercise returns the same result.
    $this->postJson("/api/grammar-exercises/{$cloze->id}/check", ['given' => 'has lost'])
        ->assertOk()->assertJson(['correct' => false, 'outcome' => 'answer_shown']);
});

test('an exercise without a hint gets a generic one that never contains the answer', function () {
    $rule = practiceRule();
    addExercises($rule, [fiveTypeItems()[2]], ['hint' => null]);
    learner();
    $cloze = exerciseOf($rule, 'cloze');

    $hint = $this->postJson("/api/grammar-exercises/{$cloze->id}/check", ['given' => 'x'])->json('hint');

    expect($hint)->toBeString()->not->toContain('has lost');
});

test('a reported exercise is hidden for the learner at once and replaced in the round', function () {
    $rule = practiceRule();
    addExercises($rule, [...fiveTypeItems(), ...fiveTypeItems(' again')]);
    $user = learner();
    $bad = exerciseOf($rule, 'fix');
    $roundIds = $rule->exercises()->where('id', '!=', $bad->id)->where('type', '!=', 'fix')->pluck('id')->all();

    $replacement = $this->postJson("/api/grammar-exercises/{$bad->id}/report", [
        'level' => 'hard', 'round_exercise_ids' => $roundIds, 'reason' => 'Two answers are correct',
    ])->assertOk()->json('replacement');

    expect($replacement['type'])->toBe('fix')
        ->and($replacement['id'])->not->toBe($bad->id)
        ->and(GrammarExerciseReport::query()->where('user_id', $user->id)->count())->toBe(1);

    $this->postJson("/api/grammar-exercises/{$bad->id}/check", ['given' => 'x'])->assertNotFound();
    $ids = array_column($this->postJson("/api/grammar-rules/{$rule->id}/practice/rounds", ['level' => 'medium', 'count' => 10])->json('exercises'), 'id');
    expect($ids)->not->toContain($bad->id);
});

test('three reports hide an exercise for everyone; admin drafts and archived ones are never shown', function () {
    $rule = practiceRule();
    addExercises($rule, [fiveTypeItems()[0]]);
    addExercises($rule, [fiveTypeItems()[2]], ['origin' => GrammarRuleExercise::ORIGIN_ADMIN]);
    addExercises($rule, [fiveTypeItems()[3]], ['status' => GrammarRuleExercise::STATUS_ARCHIVED]);
    addExercises($rule, [fiveTypeItems()[4]], ['origin' => GrammarRuleExercise::ORIGIN_ADMIN, 'status' => GrammarRuleExercise::STATUS_PUBLISHED]);
    $reported = exerciseOf($rule, 'multiple_choice');
    foreach (User::factory()->count(3)->create() as $other) {
        GrammarExerciseReport::query()->create(['user_id' => $other->id, 'grammar_rule_exercise_id' => $reported->id]);
    }
    learner();

    $this->getJson("/api/grammar-rules/{$rule->id}/practice")
        ->assertOk()
        ->assertJson(['available_count' => 1, 'unseen_count' => 1]);
});

test('a finished round writes one practice attempt, joins My grammar and updates confidence', function () {
    $rule = practiceRule();
    addExercises($rule, fiveTypeItems());
    $user = learner();
    $ids = fn (string $type) => exerciseOf($rule, $type)->id;
    $check = fn (string $type, ?string $given) => $this->postJson('/api/grammar-exercises/'.$ids($type).'/check', ['given' => $given])->assertOk();

    $check('multiple_choice', '1');
    $check('build', 'Have you ever been to Japan?');
    $check('cloze', 'has losed');
    $check('cloze', 'has lost');
    $check('transform', 'They finished?');
    $check('transform', 'They finished?');
    $this->postJson('/api/grammar-exercises/'.$ids('fix').'/report', ['level' => 'medium'])->assertOk();

    $result = $this->postJson("/api/grammar-rules/{$rule->id}/practice/complete", [
        'level' => 'medium',
        'items' => [
            ['exercise_id' => $ids('multiple_choice'), 'ms' => 3000],
            ['exercise_id' => $ids('build'), 'ms' => 6000],
            ['exercise_id' => $ids('cloze'), 'ms' => 9000],
            ['exercise_id' => $ids('transform'), 'outcome' => 'first_try', 'ms' => 7000],
            ['exercise_id' => $ids('fix'), 'outcome' => 'reported', 'ms' => 2000],
        ],
    ])->assertCreated()->json();

    // (2 first try + 0.5 × 1 after hint) / 4 scored = 62.5 %
    expect($result['score_pct'])->toBe(62.5)
        ->and($result)->toMatchArray(['first_try' => 2, 'after_hint' => 1, 'missed' => 1, 'reported' => 1, 'in_my_list' => true, 'can_mark_learned' => false])
        ->and($result['confidence_before'])->toBeNull()
        ->and($result['confidence_after'])->toBe(62.5)
        ->and(array_column($result['to_review'], 'answer'))->toBe(['has lost', 'Have they finished?']);

    $attempt = GrammarExamAttempt::query()->sole();
    expect($attempt->type)->toBe(GrammarExamAttempt::TYPE_PRACTICE)
        ->and($attempt->level)->toBe('medium')
        ->and($attempt->total_cards)->toBe(4)
        ->and($attempt->items[0])->toMatchArray(['type' => 'multiple_choice', 'level' => 'easy', 'outcome' => 'first_try', 'given' => 'have seen', 'ms' => 3000])
        ->and($attempt->items[3]['outcome'])->toBe('answer_shown');

    $progress = UserGrammarRule::query()->where('user_id', $user->id)->sole();
    expect($progress->status)->toBe(UserGrammarRule::STATUS_LEARNING)
        ->and($progress->confidence_calculated)->toBe(62.5);

    // Few unseen exercises left → the background top-up listener was queued.
    Queue::assertPushed(CallQueuedListener::class, fn (CallQueuedListener $job) => $job->class === TopUpGrammarExercisePool::class);

    $this->getJson("/api/grammar-rules/{$rule->id}/practice")
        ->assertJson(['last_result' => ['score_pct' => 62.5, 'level' => 'medium'], 'in_my_list' => true, 'unseen_count' => 0]);
});

test('a strong Medium or Hard round offers Mark as learned, an Easy one does not', function (string $level, bool $offered) {
    $rule = practiceRule();
    addExercises($rule, fiveTypeItems());
    learner();
    $choice = exerciseOf($rule, 'multiple_choice');
    $this->postJson("/api/grammar-exercises/{$choice->id}/check", ['given' => '1'])->assertOk();

    $this->postJson("/api/grammar-rules/{$rule->id}/practice/complete", [
        'level' => $level,
        'items' => [['exercise_id' => $choice->id]],
    ])->assertCreated()->assertJson(['score_pct' => 100, 'can_mark_learned' => $offered, 'learned' => false]);
})->with([['easy', false], ['medium', true], ['hard', true]]);

test('closing a round early writes nothing', function () {
    $rule = practiceRule();
    addExercises($rule, fiveTypeItems());
    learner();

    $this->postJson("/api/grammar-rules/{$rule->id}/practice/rounds", ['level' => 'medium', 'count' => 5])->assertOk();
    $this->postJson('/api/grammar-exercises/'.exerciseOf($rule, 'cloze')->id.'/check', ['given' => 'has lost'])->assertOk();

    expect(GrammarExamAttempt::query()->count())->toBe(0)
        ->and(UserGrammarRule::query()->count())->toBe(0);
});

test('a round of only reported or foreign exercises is rejected', function () {
    $rule = practiceRule();
    $other = practiceRule();
    addExercises($other, [fiveTypeItems()[2]]);
    learner();

    $this->postJson("/api/grammar-rules/{$rule->id}/practice/complete", [
        'level' => 'hard',
        'items' => [['exercise_id' => exerciseOf($other, 'cloze')->id, 'outcome' => 'first_try', 'given' => 'has lost']],
    ])->assertUnprocessable();

    expect(GrammarExamAttempt::query()->count())->toBe(0);
});

test('practice mistakes replays exactly the chosen exercises', function () {
    $rule = practiceRule();
    addExercises($rule, fiveTypeItems());
    learner();
    $chosen = [exerciseOf($rule, 'fix')->id, exerciseOf($rule, 'multiple_choice')->id];

    $round = $this->postJson("/api/grammar-rules/{$rule->id}/practice/rounds", ['level' => 'medium', 'count' => 5, 'exercise_ids' => $chosen])
        ->assertOk()->json('exercises');

    expect(array_column($round, 'type'))->toBe(['multiple_choice', 'fix']);
});

test('unpublished rules and guests cannot practice', function () {
    $draft = practiceRule(GrammarRule::STATUS_DRAFT);

    $this->getJson("/api/grammar-rules/{$draft->id}/practice")->assertUnauthorized();

    learner();
    $this->getJson("/api/grammar-rules/{$draft->id}/practice")->assertNotFound();
});

test('only the owner can practice a personal grammar rule', function () {
    $owner = User::factory()->create();
    $rule = practiceRule(GrammarRule::STATUS_PERSONAL);
    $rule->update(['owner_user_id' => $owner->id]);
    addExercises($rule, fiveTypeItems());

    Sanctum::actingAs($owner);
    $this->getJson("/api/grammar-rules/{$rule->id}/practice")
        ->assertOk()
        ->assertJsonPath('available_count', 5);

    $other = User::factory()->create();
    Sanctum::actingAs($other);
    $this->getJson("/api/grammar-rules/{$rule->id}/practice")->assertNotFound();
    $this->postJson("/api/grammar-rules/{$rule->id}/practice/rounds", ['level' => 'medium', 'count' => 5])->assertNotFound();
    $this->postJson('/api/grammar-exercises/'.exerciseOf($rule, 'fix')->id.'/check', ['given' => 'I saw him yesterday.'])->assertNotFound();
});

test('the server decides the outcome: a shown answer cannot be claimed as first try', function () {
    $rule = practiceRule();
    addExercises($rule, fiveTypeItems());
    learner();
    $cloze = exerciseOf($rule, 'cloze');
    $choice = exerciseOf($rule, 'multiple_choice');

    $this->postJson("/api/grammar-rules/{$rule->id}/practice/rounds", ['level' => 'medium', 'count' => 5])->assertOk();
    $this->postJson("/api/grammar-exercises/{$cloze->id}/check", ['given' => null, 'show_answer' => true])->assertJson(['answer' => 'has lost']);
    // Sending "attempt 1" again does not buy another hint.
    $this->postJson("/api/grammar-exercises/{$choice->id}/check", ['given' => '0', 'attempt' => 1])->assertJsonStructure(['hint']);
    $this->postJson("/api/grammar-exercises/{$choice->id}/check", ['given' => '2', 'attempt' => 1])->assertJson(['outcome' => 'answer_shown']);

    $result = $this->postJson("/api/grammar-rules/{$rule->id}/practice/complete", [
        'level' => 'medium',
        'items' => [
            ['exercise_id' => $cloze->id, 'outcome' => 'first_try', 'given' => 'has lost'],
            ['exercise_id' => $cloze->id, 'outcome' => 'first_try', 'given' => 'has lost'],
            ['exercise_id' => $choice->id, 'outcome' => 'first_try', 'given' => '1'],
        ],
    ])->assertCreated()->json();

    expect($result)->toMatchArray(['score_pct' => 0, 'first_try' => 0, 'missed' => 2])
        ->and(GrammarExamAttempt::query()->sole()->items)->toHaveCount(2);
});

test('exercises that were never answered are not scored', function () {
    $rule = practiceRule();
    addExercises($rule, fiveTypeItems());
    learner();

    $this->postJson("/api/grammar-rules/{$rule->id}/practice/complete", [
        'level' => 'medium',
        'items' => [['exercise_id' => exerciseOf($rule, 'cloze')->id, 'outcome' => 'first_try', 'given' => 'has lost']],
    ])->assertUnprocessable();
});

test('a practice-mistakes replay is scored for the screen but not saved', function () {
    $rule = practiceRule();
    addExercises($rule, fiveTypeItems());
    learner();
    $cloze = exerciseOf($rule, 'cloze');

    $this->postJson("/api/grammar-rules/{$rule->id}/practice/rounds", ['level' => 'hard', 'count' => 5, 'exercise_ids' => [$cloze->id]])->assertOk();
    $this->postJson("/api/grammar-exercises/{$cloze->id}/check", ['given' => 'has lost'])->assertJson(['correct' => true]);

    $this->postJson("/api/grammar-rules/{$rule->id}/practice/complete", [
        'level' => 'hard', 'replay' => true,
        'items' => [['exercise_id' => $cloze->id, 'outcome' => 'first_try', 'given' => 'has lost']],
    ])->assertCreated()->assertJson(['score_pct' => 100, 'can_mark_learned' => false, 'attempt_id' => null]);

    expect(GrammarExamAttempt::query()->count())->toBe(0)
        ->and(UserGrammarRule::query()->count())->toBe(0);
});

test('a new round forgets answers from a round that was closed early', function () {
    $rule = practiceRule();
    addExercises($rule, fiveTypeItems());
    learner();
    $cloze = exerciseOf($rule, 'cloze');

    $this->postJson("/api/grammar-rules/{$rule->id}/practice/rounds", ['level' => 'medium', 'count' => 5])->assertOk();
    $this->postJson("/api/grammar-exercises/{$cloze->id}/check", ['given' => null, 'show_answer' => true]);

    $this->postJson("/api/grammar-rules/{$rule->id}/practice/rounds", ['level' => 'medium', 'count' => 5])->assertOk();
    $this->postJson("/api/grammar-exercises/{$cloze->id}/check", ['given' => 'has lost'])->assertJson(['correct' => true, 'outcome' => 'first_try']);
});

test('with AI down a hard round falls back to the exercises that exist', function () {
    config(['ai.enabled' => false]);
    $rule = practiceRule();
    addExercises($rule, [fiveTypeItems()[0]]);
    learner();

    $this->postJson("/api/grammar-rules/{$rule->id}/practice/rounds", ['level' => 'hard', 'count' => 5])
        ->assertOk()
        ->assertJsonPath('exercises.0.type', 'multiple_choice');
});

test('exercises of an unpublished rule cannot be checked or reported', function () {
    $rule = practiceRule();
    addExercises($rule, fiveTypeItems());
    $rule->update(['status' => GrammarRule::STATUS_DRAFT]);
    learner();
    $cloze = exerciseOf($rule, 'cloze');

    $this->postJson("/api/grammar-exercises/{$cloze->id}/check", ['given' => null, 'show_answer' => true])->assertNotFound();
    $this->postJson("/api/grammar-exercises/{$cloze->id}/report", ['level' => 'hard'])->assertOk()->assertJson(['replacement' => null]);
    expect(GrammarExerciseReport::query()->count())->toBe(0);
});

test('a reported item counts only when this learner reported that exercise of this rule', function () {
    $rule = practiceRule();
    $other = practiceRule();
    addExercises($rule, fiveTypeItems());
    addExercises($other, [fiveTypeItems()[2]]);
    learner();
    $cloze = exerciseOf($rule, 'cloze');
    $fix = exerciseOf($rule, 'fix');

    $this->postJson("/api/grammar-exercises/{$fix->id}/report", ['level' => 'hard'])->assertOk();
    $this->postJson("/api/grammar-exercises/{$cloze->id}/check", ['given' => 'has lost'])->assertOk();

    $result = $this->postJson("/api/grammar-rules/{$rule->id}/practice/complete", [
        'level' => 'hard',
        'items' => [
            ['exercise_id' => $cloze->id, 'outcome' => 'first_try'],
            ['exercise_id' => $fix->id, 'outcome' => 'reported'],
            ['exercise_id' => exerciseOf($other, 'cloze')->id, 'outcome' => 'reported'],
            ['exercise_id' => exerciseOf($rule, 'transform')->id, 'outcome' => 'reported'],
        ],
    ])->assertCreated()->json();

    expect($result)->toMatchArray(['first_try' => 1, 'reported' => 1]);
});
