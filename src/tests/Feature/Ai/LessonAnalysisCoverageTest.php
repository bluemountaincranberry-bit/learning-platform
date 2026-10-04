<?php

use App\Contracts\Ai\AiJsonClient;
use App\Contracts\Ai\LessonAnalysisStoreInterface;
use App\Contracts\Ai\PromptRegistryInterface;
use App\Modules\Ai\Application\Agent\Tracing\TracedLlmCall;
use App\Modules\Ai\Application\LessonAnalysisService;
use App\Modules\Content\Application\Contracts\PdfTextExtractorInterface;
use App\Modules\Learning\Domain\Models\Lesson;
use App\Modules\Learning\Domain\Models\LessonAnalysisRun;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * Coverage of lesson analysis against a lesson PDF with known items (VIK-70).
 *
 * The first test is deterministic and always runs: it proves that nothing in
 * the document — including its last item — is dropped before the model, by
 * feeding every chunk to a "model" that echoes the items it is shown.
 * The second measures real recall against the configured provider and only
 * runs on demand (costs money, needs network):
 *
 *   AI_COVERAGE_EVAL=1 make test ARGS="--filter=LessonAnalysisCoverageTest"
 */

uses(RefreshDatabase::class);

function coverageFixture(): array
{
    $expected = json_decode(file_get_contents(base_path('tests/Fixtures/pdf/wordlist-unit-1d.expected.json')), true)['items'];
    $text = app(PdfTextExtractorInterface::class)->extractFromPath(base_path('tests/Fixtures/pdf/wordlist-unit-1d.pdf'));

    return [$text, $expected];
}

function coverageNormalize(string $s): string
{
    $s = preg_replace('/\[[^\]]*\]/u', ' ', str_replace('’', "'", $s));

    return trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z\s\']+/u', ' ', mb_strtolower($s))));
}

function coverageRun(string $text): LessonAnalysisRun
{
    $user = User::factory()->create();
    $lesson = Lesson::query()->create(['user_id' => $user->id, 'status' => Lesson::STATUS_ACTIVE, 'source_text' => $text]);

    return $lesson->analysisRuns()->create(['status' => LessonAnalysisRun::STATUS_PENDING]);
}

function coverageRecall(LessonAnalysisRun $run, array $expected): array
{
    $found = $run->lexemeCandidates()->pluck('text')->map(fn ($t) => coverageNormalize($t))->all();
    $missing = array_values(array_filter($expected, function ($key) use ($found) {
        foreach ($found as $text) {
            if (str_contains(preg_replace('/\s+/', ' ', $text), preg_replace('/\s+/', ' ', coverageNormalize($key)))) {
                return false;
            }
        }

        return true;
    }));

    return [1 - count($missing) / count($expected), $missing];
}

test('every item of a long PDF, including the last, reaches the model', function () {
    [$text, $expected] = coverageFixture();
    expect(mb_strlen($text))->toBeGreaterThan((int) config('ai.analysis.lesson_chunk_chars'));

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->andReturnUsing(function ($system, $user) use ($expected) {
        $shown = coverageNormalize($user);
        $items = array_values(array_filter($expected, fn ($key) => str_contains($shown, coverageNormalize($key))));

        return ['lexemes' => array_map(fn ($key) => ['text' => $key], $items), 'grammar' => []];
    });

    $run = coverageRun($text);
    (new LessonAnalysisService($client, app(TracedLlmCall::class), app(PromptRegistryInterface::class), app(LessonAnalysisStoreInterface::class)))
        ->analyze($run->id);

    [$recall, $missing] = coverageRecall($run, $expected);
    expect($missing)->toBe([])->and($recall)->toEqual(1.0)
        ->and($run->lexemeCandidates()->pluck('text')->all())->toContain("playing devil's advocate");
});

test('live model also offers the glossed expressions hidden in the examples', function () {
    [$text] = coverageFixture();
    $bonus = json_decode(file_get_contents(base_path('tests/Fixtures/pdf/wordlist-unit-1d.expected.json')), true)['bonus_items'];

    $run = coverageRun($text);
    app(LessonAnalysisService::class)->analyze($run->id);

    [$recall, $missing] = coverageRecall($run, $bonus);
    expect($recall)->toBeGreaterThanOrEqual(0.33, 'missing bonus: '.implode('; ', $missing));
})->skip(fn () => ! env('AI_COVERAGE_EVAL'), 'Set AI_COVERAGE_EVAL=1 to run against the real provider.');

test('live model finds substantially all items of the PDF word list', function () {
    [$text, $expected] = coverageFixture();

    $run = coverageRun($text);
    app(LessonAnalysisService::class)->analyze($run->id);

    [$recall, $missing] = coverageRecall($run, $expected);
    expect($recall)->toBeGreaterThanOrEqual((float) env('AI_COVERAGE_MIN_RECALL', 0.95), 'missing: '.implode('; ', $missing).' | found '.$run->lexemeCandidates()->count().': '.$run->lexemeCandidates()->pluck('text')->implode(' | '));
})->skip(fn () => ! env('AI_COVERAGE_EVAL'), 'Set AI_COVERAGE_EVAL=1 to run against the real provider.');
