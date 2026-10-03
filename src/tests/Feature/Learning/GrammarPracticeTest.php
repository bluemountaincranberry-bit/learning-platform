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

    $this->postJson("/api/grammar-exercises/{$exercise->id}/check", ['given' => $right, 'attempt' => 1])
        ->assertOk()->assertJson(['correct' => true, 'outcome' => 'first_try']);
    $this->postJson("/api/grammar-exercises/{$exercise->id}/check", ['given' => $wrong, 'attempt' => 1])
        ->assertOk()->assertJson(['correct' => false]);
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

    $first = $this->postJson("/api/grammar-exercises/{$choice->id}/check", ['given' => '0', 'attempt' => 1])
        ->assertOk()
        ->assertJson(['correct' => false, 'hint' => 'Twice = experience up to now.', 'struck_option_index' => 0])
        ->json();
    expect($first)->not->toHaveKeys(['answer', 'explanation']);

    $this->postJson("/api/grammar-exercises/{$choice->id}/check", ['given' => '1', 'attempt' => 2])
        ->assertOk()->assertJson(['correct' => true, 'outcome' => 'after_hint']);

    $this->postJson("/api/grammar-exercises/{$cloze->id}/check", ['given' => 'has losed', 'attempt' => 2])
        ->assertOk()
        ->assertJson(['correct' => false, 'outcome' => 'answer_shown', 'answer' => 'has lost', 'explanation' => 'lose – lost – lost.']);

    $this->postJson("/api/grammar-exercises/{$cloze->id}/check", ['given' => null, 'attempt' => 1, 'show_answer' => true])
        ->assertOk()->assertJson(['outcome' => 'answer_shown', 'answer' => 'has lost']);
});

test('an exercise without a hint gets a generic one that never contains the answer', function () {
    $rule = practiceRule();
    addExercises($rule, [fiveTypeItems()[2]], ['hint' => null]);
    learner();
    $cloze = exerciseOf($rule, 'cloze');

    $hint = $this->postJson("/api/grammar-exercises/{$cloze->id}/check", ['given' => 'x', 'attempt' => 1])->json('hint');

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

    $this->postJson("/api/grammar-exercises/{$bad->id}/check", ['given' => 'x', 'attempt' => 1])->assertNotFound();
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

    $result = $this->postJson("/api/grammar-rules/{$rule->id}/practice/complete", [
        'level' => 'medium',
        'items' => [
            ['exercise_id' => $ids('multiple_choice'), 'outcome' => 'first_try', 'attempts' => 1, 'given' => '1', 'ms' => 3000],
            ['exercise_id' => $ids('build'), 'outcome' => 'first_try', 'attempts' => 1, 'given' => 'Have you ever been to Japan?', 'ms' => 6000],
            ['exercise_id' => $ids('cloze'), 'outcome' => 'after_hint', 'attempts' => 2, 'given' => 'has lost', 'ms' => 9000],
            // Claims success with a wrong answer → counted as missed.
            ['exercise_id' => $ids('transform'), 'outcome' => 'first_try', 'attempts' => 1, 'given' => 'They finished?', 'ms' => 7000],
            ['exercise_id' => $ids('fix'), 'outcome' => 'reported', 'attempts' => 0, 'given' => null, 'ms' => 2000],
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

    // Few unseen exercises left → a background top-up was queued.
    Queue::assertPushed(GenerateGrammarExercisesJob::class);

    $this->getJson("/api/grammar-rules/{$rule->id}/practice")
        ->assertJson(['last_result' => ['score_pct' => 62.5, 'level' => 'medium'], 'in_my_list' => true, 'unseen_count' => 0]);
});

test('a strong Medium or Hard round offers Mark as learned, an Easy one does not', function (string $level, bool $offered) {
    $rule = practiceRule();
    addExercises($rule, fiveTypeItems());
    learner();
    $choice = exerciseOf($rule, 'multiple_choice');

    $this->postJson("/api/grammar-rules/{$rule->id}/practice/complete", [
        'level' => $level,
        'items' => [['exercise_id' => $choice->id, 'outcome' => 'first_try', 'attempts' => 1, 'given' => '1']],
    ])->assertCreated()->assertJson(['score_pct' => 100, 'can_mark_learned' => $offered, 'learned' => false]);
})->with([['easy', false], ['medium', true], ['hard', true]]);

test('closing a round early writes nothing', function () {
    $rule = practiceRule();
    addExercises($rule, fiveTypeItems());
    learner();

    $this->postJson("/api/grammar-rules/{$rule->id}/practice/rounds", ['level' => 'medium', 'count' => 5])->assertOk();
    $this->postJson('/api/grammar-exercises/'.exerciseOf($rule, 'cloze')->id.'/check', ['given' => 'has lost', 'attempt' => 1])->assertOk();

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
