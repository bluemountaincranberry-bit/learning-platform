<?php

use App\Modules\Content\Application\Data\GrammarExerciseHistory;
use App\Modules\Content\Application\Data\GrammarPracticeExercise;
use App\Modules\Learning\Application\GrammarPractice\GrammarPracticeLevel;
use App\Modules\Learning\Application\GrammarPractice\GrammarRoundComposer;
use Carbon\CarbonImmutable;

function practiceExercise(int $id, string $type): GrammarPracticeExercise
{
    return new GrammarPracticeExercise(
        id: $id, ruleId: 1, type: $type,
        level: in_array($type, ['multiple_choice', 'build'], true) ? 'easy' : 'hard',
        instruction: null, prompt: "Prompt {$id}", options: null, tiles: null, answer: 'x',
        answerIndex: null, acceptedAnswers: [], hint: null, explanation: null, origin: 'ai',
    );
}

/** @return list<GrammarPracticeExercise> three of each type, ids 1..15 */
function fullPool(): array
{
    $pool = [];
    $id = 1;
    foreach (['multiple_choice', 'build', 'cloze', 'transform', 'fix'] as $type) {
        foreach (range(1, 3) as $_) {
            $pool[] = practiceExercise($id++, $type);
        }
    }

    return $pool;
}

function typesOf(array $round): array
{
    return array_map(fn (GrammarPracticeExercise $e) => $e->type, $round);
}

test('medium round of 10 takes two of each type, easy to hard', function () {
    $round = (new GrammarRoundComposer)->compose(fullPool(), [], GrammarPracticeLevel::Medium, 10);

    expect(typesOf($round))->toBe([
        'multiple_choice', 'multiple_choice', 'build', 'build', 'cloze', 'cloze',
        'transform', 'transform', 'fix', 'fix',
    ]);
});

test('easy round uses only choose and build, hard only fill, transform and fix', function () {
    $composer = new GrammarRoundComposer;

    expect(array_values(array_unique(typesOf($composer->compose(fullPool(), [], GrammarPracticeLevel::Easy, 5)))))
        ->toBe(['multiple_choice', 'build'])
        ->and(array_values(array_unique(typesOf($composer->compose(fullPool(), [], GrammarPracticeLevel::Hard, 5)))))
        ->toBe(['cloze', 'transform', 'fix']);
});

test('a short type is topped up from the next type at the same level, never from the other level', function () {
    $pool = [practiceExercise(1, 'multiple_choice'), ...array_map(fn ($i) => practiceExercise($i, 'build'), range(2, 6)), practiceExercise(7, 'cloze')];

    $round = (new GrammarRoundComposer)->compose($pool, [], GrammarPracticeLevel::Easy, 5);

    expect(typesOf($round))->toBe(['multiple_choice', 'build', 'build', 'build', 'build']);
});

test('never seen first, then missed last time, then seen longest ago, no repeats', function () {
    $pool = array_map(fn ($i) => practiceExercise($i, 'cloze'), range(1, 4));
    $now = CarbonImmutable::parse('2026-10-03 12:00');
    $history = [
        1 => new GrammarExerciseHistory(1, $now->subDay(), 'first_try'),
        2 => new GrammarExerciseHistory(2, $now->subHour(), 'answer_shown'),
        3 => new GrammarExerciseHistory(3, $now->subDays(5), 'first_try'),
    ];

    $round = (new GrammarRoundComposer)->compose($pool, $history, GrammarPracticeLevel::Hard, 4);

    expect(array_map(fn ($e) => $e->id, $round))->toBe([4, 2, 3, 1]);
});

test('the round is shorter when the pool is small and excluded exercises are skipped', function () {
    $pool = array_map(fn ($i) => practiceExercise($i, 'fix'), range(1, 3));

    $round = (new GrammarRoundComposer)->compose($pool, [], GrammarPracticeLevel::Hard, 10, exclude: [2]);

    expect(array_map(fn ($e) => $e->id, $round))->toBe([1, 3]);
});
