<?php

namespace App\Modules\Ai\Application\Agent\Tools\Student;

use App\Exceptions\AgentToolException;
use App\Modules\Ai\Application\Agent\Contracts\AgentTool;
use App\Modules\Ai\Application\Agent\Data\AgentToolContext;
use App\Modules\Ai\Application\Agent\Data\AgentToolDefinition;
use App\Contracts\Ai\AiJsonClient;
use App\Modules\Srs\Application\Contracts\ReviewScheduleReaderInterface;

/**
 * Learning tool (task 3.7): proposes a short draft quiz over structured
 * output, reusing the `AiJsonClient::completeJson()` pattern
 * `AiContentAnalysisService` already uses for candidate extraction — see
 * `ai-engineering-learning-roadmap.md` step 3.
 *
 * `sideEffect = draft_only`, same reasoning as `ContentAgentService`'s
 * content candidates (ADR-001, `agent_human_in_the_loop_apply` project
 * memory): this tool never writes to `user_lexeme_progress` or any other
 * live state. It only returns a proposed set of questions; the student
 * answers them through the app's normal, non-agentic exercise flow, exactly
 * like `ExerciseAgent`'s draft step in `ai-platform-vision.md` section 5.
 * This is a `Tool`, not an `Agent` (ADR-002): one structured completion call
 * with ready inputs, no multi-step reasoning loop of its own.
 */
class GenerateQuizTool implements AgentTool
{
    public function __construct(
        private readonly AiJsonClient $client,
        private readonly ReviewScheduleReaderInterface $reviewSchedule,
    ) {}

    public function definition(): AgentToolDefinition
    {
        return new AgentToolDefinition(
            name: 'generate_quiz',
            description: 'Proposes a short draft quiz (multiple-choice/gap-fill questions) for the student to practice. Never records anything — the student answers through the normal exercise flow.',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'words' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                        'description' => 'Optional specific words/phrases to quiz on. Omit to default to the student\'s current spaced-repetition review queue.',
                    ],
                    'count' => [
                        'type' => 'integer',
                        'description' => 'Number of questions to generate (default 5, max 10).',
                    ],
                ],
                'required' => [],
            ],
            sideEffect: AgentToolDefinition::SIDE_EFFECT_DRAFT_ONLY,
        );
    }

    public function execute(array $arguments, AgentToolContext $context): array
    {
        $count = min(10, max(1, (int) ($arguments['count'] ?? config('ai.exercises.default_count', 5))));

        $words = is_array($arguments['words'] ?? null)
            ? array_values(array_filter(array_map(
                fn ($word) => trim((string) $word),
                $arguments['words']
            ), fn ($word) => $word !== ''))
            : [];

        if ($words === []) {
            $words = $this->reviewSchedule->wordsForQuiz($context->actingUserId, $count);
        }

        if ($words === []) {
            return [
                'quiz' => [],
                'note' => 'Not enough vocabulary data yet to generate a quiz — the student has no words in their review queue.',
            ];
        }

        try {
            $result = $this->client->completeJson(
                $this->systemPrompt($count),
                'Words/phrases: '.implode(', ', $words),
                $this->responseSchema()
            );
        } catch (\Throwable $e) {
            throw new AgentToolException('Could not generate a quiz right now: '.$e->getMessage());
        }

        $questions = $this->sanitizeQuestions(is_array($result['questions'] ?? null) ? $result['questions'] : []);

        return [
            'quiz' => $questions,
            'is_draft' => true,
            'note' => 'This is a draft quiz proposal — nothing is recorded automatically. The student answers through the normal exercise flow.',
        ];
    }

    private function systemPrompt(int $count): string
    {
        return 'You are a language-learning quiz generator. Given a list of words/phrases the student is '
            ."learning, generate exactly {$count} short questions testing them — a mix of multiple-choice and "
            .'gap-fill is fine. Keep questions short and unambiguous, with exactly one correct answer.';
    }

    /**
     * @return array<string, mixed>
     */
    private function responseSchema(): array
    {
        return [
            'questions' => [
                ['type' => 'multiple_choice|gap_fill', 'prompt' => 'string', 'choices' => ['string', '...'], 'answer' => 'string'],
            ],
        ];
    }

    /**
     * @param  array<int, mixed>  $questions
     * @return array<int, array<string, mixed>>
     */
    private function sanitizeQuestions(array $questions): array
    {
        $sanitized = [];

        foreach ($questions as $question) {
            if (! is_array($question)) {
                continue;
            }

            $type = $question['type'] ?? null;
            $prompt = trim((string) ($question['prompt'] ?? ''));
            $answer = trim((string) ($question['answer'] ?? ''));

            if (! in_array($type, ['multiple_choice', 'gap_fill'], true) || $prompt === '' || $answer === '') {
                continue;
            }

            $item = ['type' => $type, 'prompt' => $prompt, 'answer' => $answer];

            if ($type === 'multiple_choice') {
                $choices = is_array($question['choices'] ?? null)
                    ? array_values(array_filter(array_map('strval', $question['choices']), fn ($c) => trim($c) !== ''))
                    : [];

                if (count($choices) < 2) {
                    continue;
                }

                $item['choices'] = $choices;
            }

            $sanitized[] = $item;
        }

        return $sanitized;
    }
}
