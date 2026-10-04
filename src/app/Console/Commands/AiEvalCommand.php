<?php

namespace App\Console\Commands;

use App\Contracts\Ai\ContentAnalysisCapability;
use App\Modules\Ai\Application\Agent\AgentLoop;
use App\Modules\Ai\Application\Agent\Contracts\AgentLoopObserver;
use App\Modules\Ai\Application\Agent\Data\AgentChatResponse;
use App\Modules\Ai\Application\Agent\Data\AgentToolCall;
use App\Modules\Ai\Application\Agent\Data\AgentToolContext;
use App\Modules\Ai\Application\Agent\StudentTutorAgentService;
use App\Modules\Ai\Application\Agent\Tracing\SpanRecorder;
use App\Modules\Ai\Application\Agent\Tracing\TraceContext;
use App\Modules\Ai\Application\AiGrammarRuleExampleService;
use App\Modules\Ai\Domain\Models\AiAnalysisRun;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Domain\Models\GrammarRuleExample;
use App\Modules\Content\Domain\Models\GrammarTopic;
use App\Modules\User\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Task 5.1 — evals: not a unit test ("did the code throw"), a quality check
 * against a hand-curated golden dataset, run on demand against the real
 * configured provider (`ai:eval`). Two suites:
 *
 * - `content_analysis`: golden transcripts with an expected set of
 *   words/grammar (`tests/Fixtures/Evals/content_analysis_cases.php`), run
 *   through the real `AiContentAnalysisService::analyze()`, scored by
 *   recall of the expected set (simple exact/partial substring match — see
 *   `matchCount()`), same spirit as `ai-engineering-learning-roadmap.md`
 *   step 6.
 * - `tutor_agent`: golden user messages
 *   (`tests/Fixtures/Evals/tutor_agent_cases.php`) run through the real
 *   `StudentTutorAgentService` tool set via `AgentLoop::run()`, scored by
 *   whether the model called the expected grounding tool instead of
 *   answering from parametric knowledge (the concrete risk this eval
 *   exists to catch — an ungrounded answer looks identical to a grounded
 *   one until you check which tool actually fired).
 * - `grammar_examples` (VIK-39): golden grammar rules
 *   (`tests/Fixtures/Evals/grammar_examples_cases.php`) run through the real
 *   `AiGrammarRuleExampleService`, scored against the rubric stored in that
 *   file — do the examples actually use the rule, are they marked and
 *   translated, do they cover affirmative/negative/question.
 *
 * Both suites write real rows (Content, AiAnalysisRun, User, SrsCard, ...)
 * needed to exercise the real services, wrapped in one transaction per
 * suite that is always rolled back — this command must never leave
 * fixture data behind in whatever database it's pointed at.
 */
class AiEvalCommand extends Command
{
    protected $signature = 'ai:eval {--suite=all : content_analysis | tutor_agent | grammar_examples | all}';

    protected $description = 'Run golden-dataset evals against the real AI provider and report recall/tool-grounding metrics.';

    public function handle(ContentAnalysisCapability $analysisService, AgentLoop $loop, AiGrammarRuleExampleService $exampleService): int
    {
        if (! config('ai.enabled') || (string) config('ai.openai.api_key') === '') {
            $this->error('AI is disabled or ai.openai.api_key is empty — evals call the real provider and need both.');

            return self::FAILURE;
        }

        $suite = $this->option('suite');
        $passed = true;

        if (in_array($suite, ['content_analysis', 'all'], true)) {
            $passed = $this->runContentAnalysisSuite($analysisService) && $passed;
        }

        if (in_array($suite, ['tutor_agent', 'all'], true)) {
            $passed = $this->runTutorAgentSuite($loop) && $passed;
        }

        if (in_array($suite, ['grammar_examples', 'all'], true)) {
            $passed = $this->runGrammarExamplesSuite($exampleService) && $passed;
        }

        if (! in_array($suite, ['content_analysis', 'tutor_agent', 'grammar_examples', 'all'], true)) {
            $this->error("Unknown suite '{$suite}' — expected content_analysis, tutor_agent, grammar_examples, or all.");

            return self::FAILURE;
        }

        return $passed ? self::SUCCESS : self::FAILURE;
    }

    private function runContentAnalysisSuite(ContentAnalysisCapability $service): bool
    {
        $cases = require base_path('tests/Fixtures/Evals/content_analysis_cases.php');
        $rows = [];
        $allPassed = true;

        DB::beginTransaction();

        try {
            foreach ($cases as $case) {
                $content = Content::factory()->create([
                    'source_text' => $case['transcript'],
                    'language' => $case['source_language'] ?? 'en',
                ]);
                $run = $content->analysisRuns()->create([
                    'status' => AiAnalysisRun::STATUS_PENDING,
                    'config' => ['translation_language' => $case['translation_language'] ?? 'ru'],
                ]);

                try {
                    $service->analyze($run);

                    $lexemes = $run->lexemeCandidates()->pluck('normalized_text')->map(fn ($t) => Str::lower((string) $t))->all();
                    $grammarTitles = $run->grammarCandidates()->pluck('title')->map(fn ($t) => Str::lower((string) $t))->all();

                    [$lexemeMatched, $lexemeTotal] = $this->matchCount($case['expected_lexemes'] ?? [], $lexemes);
                    [$grammarMatched, $grammarTotal] = $this->matchCount($case['expected_grammar'] ?? [], $grammarTitles);

                    $expectedTotal = $lexemeTotal + $grammarTotal;
                    $recall = $expectedTotal > 0 ? ($lexemeMatched + $grammarMatched) / $expectedTotal : 1.0;

                    $rows[] = [$case['name'], "{$lexemeMatched}/{$lexemeTotal}", "{$grammarMatched}/{$grammarTotal}", number_format($recall * 100, 0).'%'];

                    // Threshold is deliberately loose (a golden dataset of 3
                    // hand-picked cases is a smoke check, not a statistically
                    // sound benchmark) — the point is catching a prompt
                    // regression that tanks recall, not chasing 100% on
                    // inherently fuzzy LLM output.
                    if ($recall < 0.5) {
                        $allPassed = false;
                    }
                } catch (Throwable $e) {
                    $rows[] = [$case['name'], 'ERROR', 'ERROR', $e->getMessage()];
                    $allPassed = false;
                }
            }
        } finally {
            DB::rollBack();
        }

        $this->info('content_analysis eval (recall of expected lexemes/grammar against golden transcripts):');
        $this->table(['case', 'lexemes matched', 'grammar matched', 'recall'], $rows);

        return $allPassed;
    }

    private function runTutorAgentSuite(AgentLoop $loop): bool
    {
        $cases = require base_path('tests/Fixtures/Evals/tutor_agent_cases.php');
        $blueprint = StudentTutorAgentService::blueprint();
        $tools = array_map(fn (string $toolClass) => app($toolClass), $blueprint->tools);
        $rows = [];
        $allPassed = true;

        DB::beginTransaction();

        try {
            foreach ($cases as $case) {
                $user = User::factory()->create();
                ($case['setup'])($user);

                $trace = TraceContext::newTrace();
                $context = new AgentToolContext(conversationId: 0, actingUserId: $user->id, trace: $trace);
                $observer = new class implements AgentLoopObserver
                {
                    /** @var array<int, string> */
                    public array $toolNames = [];

                    public ?string $finalText = null;

                    public function onToolCallStarted(AgentToolCall $call): void {}

                    public function onToolCallCompleted(AgentToolCall $call, array $result, int $latencyMs): void
                    {
                        $this->toolNames[] = $call->name;
                    }

                    public function onFinalResponse(AgentChatResponse $response): void
                    {
                        $this->finalText = $response->content;
                    }

                    public function onIterationLimitReached(): void {}
                };

                try {
                    $loop->run(
                        systemPrompt: $blueprint->systemPrompt,
                        startingMessages: [['role' => 'user', 'content' => $case['message']]],
                        tools: $tools,
                        maxIterations: $blueprint->maxIterations,
                        context: $context,
                        observer: $observer,
                        trace: $trace,
                        spanRecorder: app(SpanRecorder::class),
                        turnMetadata: ['agent_type' => 'eval:'.$case['name']],
                    );

                    $toolMatch = array_intersect($case['expected_tools'], $observer->toolNames) !== [];
                    $reply = Str::lower((string) $observer->finalText);
                    $keywordHits = array_filter($case['expected_keywords'] ?? [], fn (string $kw) => str_contains($reply, Str::lower($kw)));

                    $rows[] = [
                        $case['name'],
                        implode(',', $observer->toolNames) ?: '(none)',
                        $toolMatch ? 'yes' : 'no',
                        count($keywordHits).'/'.count($case['expected_keywords'] ?? []),
                    ];

                    if (! $toolMatch) {
                        $allPassed = false;
                    }
                } catch (Throwable $e) {
                    $rows[] = [$case['name'], 'ERROR', 'no', $e->getMessage()];
                    $allPassed = false;
                }
            }
        } finally {
            DB::rollBack();
        }

        $this->info('tutor_agent eval (did the model ground its answer in the expected tool call):');
        $this->table(['case', 'tools called', 'expected tool called', 'keyword hits'], $rows);

        return $allPassed;
    }

    private function runGrammarExamplesSuite(AiGrammarRuleExampleService $service): bool
    {
        $cases = require base_path('tests/Fixtures/Evals/grammar_examples_cases.php');
        $rows = [];
        $misses = [];
        $allPassed = true;

        DB::beginTransaction();

        try {
            $topic = GrammarTopic::query()->create(['slug' => 'eval-'.Str::random(8), 'language' => 'en', 'name' => 'Eval', 'status' => 'active']);

            foreach ($cases as $case) {
                $rule = GrammarRule::query()->create([
                    'topic_id' => $topic->id,
                    'slug' => 'eval-'.$case['name'].'-'.Str::random(6),
                    'language' => 'en',
                    'title' => $case['title'],
                    'level' => $case['level'],
                    'summary' => $case['summary'],
                    'status' => GrammarRule::STATUS_PUBLISHED,
                ]);

                try {
                    $service->generate($rule->id, 8, 'ru');
                    $score = $this->scoreGrammarExamples($case, $rule->examples()->get()->all());

                    $passed = $score['count'] >= $case['min_examples']
                        && $score['marked_translated'] === $score['count']
                        && $score['uses_rule'] >= 0.8 * $score['count']
                        && $score['missing_kinds'] === []
                        // A typical error stored as the correct sentence teaches
                        // wrong grammar: one is enough to fail.
                        && $score['forbidden_hits'] === 0;

                    $rows[] = [
                        $case['name'],
                        (string) $score['count'],
                        "{$score['uses_rule']}/{$score['count']}",
                        "{$score['form_marked']}/{$score['count']}",
                        "{$score['marked_translated']}/{$score['count']}",
                        $score['missing_kinds'] === [] ? 'all' : 'missing '.implode(',', $score['missing_kinds']),
                        $passed ? 'pass' : 'FAIL',
                    ];
                    $allPassed = $passed && $allPassed;
                    if (! $passed && $score['misses'] !== []) {
                        $misses[$case['name']] = $score['misses'];
                    }
                } catch (Throwable $e) {
                    $rows[] = [$case['name'], '0', 'ERROR', 'ERROR', 'ERROR', $e->getMessage(), 'FAIL'];
                    $allPassed = false;
                }
            }
        } finally {
            DB::rollBack();
        }

        $this->info('grammar_examples eval (do generated examples use the rule, marked and translated, all kinds):');
        $this->table(['case', 'examples', 'uses rule', 'form marked', 'marked+translated', 'kinds', 'result'], $rows);

        foreach ($misses as $name => $lines) {
            $this->warn("{$name}: examples off the rubric");
            foreach ($lines as $line) {
                $this->line('  - '.$line);
            }
        }

        return $allPassed;
    }

    /**
     * @param  array<string, mixed>  $case
     * @param  list<GrammarRuleExample>  $examples
     * @return array{count: int, uses_rule: int, form_marked: int, marked_translated: int, missing_kinds: list<string>, misses: list<string>, forbidden_hits: int}
     */
    private function scoreGrammarExamples(array $case, array $examples): array
    {
        $usesRule = 0;
        $formMarked = 0;
        $markedTranslated = 0;
        $kinds = [];
        $misses = [];
        $forbiddenHits = 0;

        foreach ($examples as $example) {
            $chars = mb_str_split((string) $example->example);
            $marked = implode(' ', array_map(
                fn (array $span): string => implode('', array_slice($chars, $span[0], $span[1] - $span[0])),
                $example->target_spans ?? []
            ));

            if ($marked !== '' && trim((string) $example->translation) !== '') {
                $markedTranslated++;
            }

            if ($marked !== '' && preg_match($case['target_pattern'], $marked) === 1) {
                $formMarked++;
            }

            $wrongAsCorrect = collect($case['forbidden_patterns'] ?? [])->contains(fn (string $pattern): bool => preg_match($pattern, (string) $example->example) === 1);
            if ($wrongAsCorrect) {
                $forbiddenHits++;
            }
            $sentenceOk = ! $wrongAsCorrect
                && collect($case['sentence_patterns'])->every(fn (string $pattern): bool => preg_match($pattern, (string) $example->example) === 1);
            // "Uses the rule" is judged on the sentence; whether the highlight
            // covers the whole form is reported separately (a partly marked
            // question still teaches the rule, it just highlights less).
            if (preg_match($case['target_pattern'], (string) $example->example) === 1 && $sentenceOk) {
                $usesRule++;
            } else {
                $misses[] = "[{$example->kind}] {$example->example} (marked: ".($marked !== '' ? $marked : '—').')';
            }

            $kinds[] = $example->kind;
        }

        return [
            'count' => count($examples),
            'uses_rule' => $usesRule,
            'form_marked' => $formMarked,
            'marked_translated' => $markedTranslated,
            'missing_kinds' => array_values(array_diff(
                [GrammarRuleExample::KIND_AFFIRMATIVE, GrammarRuleExample::KIND_NEGATIVE, GrammarRuleExample::KIND_QUESTION],
                $kinds
            )),
            'misses' => $misses,
            'forbidden_hits' => $forbiddenHits,
        ];
    }

    /**
     * @param  array<int, string>  $expected
     * @param  array<int, string>  $actual
     * @return array{0: int, 1: int} [matched count, expected count]
     */
    private function matchCount(array $expected, array $actual): array
    {
        $matched = 0;

        foreach ($expected as $term) {
            $needle = $this->normalizeForMatch($term);

            foreach ($actual as $candidate) {
                $candidate = $this->normalizeForMatch($candidate);

                if ($candidate === $needle || str_contains($candidate, $needle) || str_contains($needle, $candidate)) {
                    $matched++;

                    continue 2;
                }
            }
        }

        return [$matched, count($expected)];
    }

    /**
     * Lightweight per-word stemming (strip trailing "ed"/"ing") on top of
     * lowercasing, so an AI-extracted inflected form ("bumped into",
     * "ended up") still matches a golden dataset entry written in base form
     * ("bump into", "end up") — a real, expected source of near-miss
     * disagreement with LLM output, not something worth hand-tuning the
     * golden dataset's wording around.
     */
    private function normalizeForMatch(string $text): string
    {
        $words = preg_split('/\s+/', trim(Str::lower($text))) ?: [];
        $stemmed = array_map(fn (string $word) => (string) preg_replace('/(ing|ed)$/', '', $word), $words);

        return implode(' ', $stemmed);
    }
}
