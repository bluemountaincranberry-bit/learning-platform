<?php

namespace App\Modules\Ai\Application\Agent\Tools\Grammar;

use App\Exceptions\AgentToolException;
use App\Modules\Ai\Application\Agent\Contracts\AgentTool;
use App\Modules\Ai\Application\Agent\Data\AgentToolContext;
use App\Modules\Ai\Application\Agent\Data\AgentToolDefinition;
use App\Contracts\Ai\AiJsonClient;

/**
 * GrammarAgent tool 3/3 (task 4.4): the step that actually makes this a
 * `GrammarAgent` instead of a slightly-fancier `ExplainGrammarTool` —
 * compares the student's own sentence against a specific candidate grammar
 * rule (found via `GrammarSearchTool`) and reports whether it actually
 * applies and what is wrong, as structured output (same
 * `AiJsonClient::completeJson()` pattern `GenerateQuizTool`/
 * `AiContentAnalysisService` already use). This is one grounded completion
 * call per invocation — still a `Tool`, not its own nested `Agent` — but it
 * is only useful as *one step* the agent chains with search/explain around
 * it, which is exactly the ADR-002 boundary example ("find the relevant
 * rule -> compare it with the student's sentence -> explain their specific
 * case") that justifies `GrammarAgent` existing as an `Agent` rather than
 * folding this into `ExplainGrammarTool`.
 *
 * `sideEffect = read_only`: this never writes anything, including to
 * `srs_reviews`/`user_lexeme_progress` — it only reports an analysis for
 * the agent to explain back to the student.
 */
class ErrorAnalysisTool implements AgentTool
{
    public function __construct(
        private readonly AiJsonClient $client,
    ) {}

    public function definition(): AgentToolDefinition
    {
        return new AgentToolDefinition(
            name: 'analyze_grammar_error',
            description: 'Checks whether a candidate grammar rule actually explains a mistake in the student\'s own sentence, and if so, what specifically is wrong. Use after grammar_search to pick which candidate is worth explaining.',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'student_sentence' => [
                        'type' => 'string',
                        'description' => 'The student\'s own sentence containing the suspected mistake.',
                    ],
                    'candidate_rule_title' => [
                        'type' => 'string',
                        'description' => 'Title of the candidate grammar rule to check against (from grammar_search).',
                    ],
                    'candidate_rule_summary' => [
                        'type' => 'string',
                        'description' => 'Summary of the candidate grammar rule, for context.',
                    ],
                ],
                'required' => ['student_sentence', 'candidate_rule_title'],
            ],
            sideEffect: AgentToolDefinition::SIDE_EFFECT_READ_ONLY,
        );
    }

    public function execute(array $arguments, AgentToolContext $context): array
    {
        $sentence = trim((string) ($arguments['student_sentence'] ?? ''));
        $title = trim((string) ($arguments['candidate_rule_title'] ?? ''));

        if ($sentence === '' || $title === '') {
            return ['error' => 'student_sentence and candidate_rule_title are required.'];
        }

        $summary = trim((string) ($arguments['candidate_rule_summary'] ?? ''));

        try {
            $result = $this->client->completeJson(
                $this->systemPrompt(),
                "Candidate grammar rule: \"{$title}\"".($summary !== '' ? " — {$summary}" : '')
                    ."\nStudent sentence: \"{$sentence}\"",
                $this->responseSchema()
            );
        } catch (\Throwable $e) {
            throw new AgentToolException('Could not analyze this sentence right now: '.$e->getMessage());
        }

        return [
            'rule_applies' => (bool) ($result['rule_applies'] ?? false),
            'has_error' => (bool) ($result['has_error'] ?? false),
            'error_description' => is_string($result['error_description'] ?? null) ? $result['error_description'] : null,
            'corrected_sentence' => is_string($result['corrected_sentence'] ?? null) ? $result['corrected_sentence'] : null,
        ];
    }

    private function systemPrompt(): string
    {
        return 'You are a grammar error analyst for a language-learning app. Given one candidate grammar rule '
            .'and a student\'s own sentence, decide: (1) whether that rule is actually the relevant one for this '
            .'sentence, (2) whether the sentence contains a mistake related to it, (3) a short description of the '
            .'mistake if any, and (4) a corrected version of the sentence if any correction is needed. '
            .'If the rule does not apply to this sentence at all, say so plainly.';
    }

    /**
     * @return array<string, mixed>
     */
    private function responseSchema(): array
    {
        return [
            'rule_applies' => 'boolean',
            'has_error' => 'boolean',
            'error_description' => 'string or null',
            'corrected_sentence' => 'string or null',
        ];
    }
}
