<?php

namespace App\Modules\Ai\Application;

use App\Exceptions\AiClientException;
use App\Modules\Ai\Application\Agent\Tracing\TraceContext;
use App\Modules\Ai\Application\Agent\Tracing\TracedLlmCall;
use App\Contracts\Ai\AiJsonClient;
use App\Contracts\Ai\PromptRegistryInterface;
use App\Modules\Content\Application\Contracts\GrammarExerciseDraftsInterface;
use App\Modules\Content\Application\Data\GrammarExerciseSource;

/**
 * Generates ready-to-use practice exercises for a grammar rule directly as
 * structured JSON (blanked sentence + correct answer, or a multiple-choice
 * stem + options + correct index) — no NLP/token-tagging of example
 * sentences involved, the model produces the finished exercise in one call,
 * the same way AiContentAnalysisService produces finished candidates.
 *
 * Generated exercises are persisted as status=draft; an admin reviews and
 * publishes them via ExercisesRelationManager before learners ever see them.
 */
class AiGrammarExerciseService
{
    public function __construct(
        private readonly AiJsonClient $client,
        private readonly TracedLlmCall $tracedCall,
        private readonly PromptRegistryInterface $promptRegistry,
        private readonly GrammarExerciseDraftsInterface $drafts,
    ) {}

    /**
     * @throws AiClientException
     */
    public function generate(int $ruleId, int $count = 5, ?string $instruction = null): int
    {
        $rule = $this->drafts->source($ruleId);
        $rendered = $this->promptRegistry->resolve(
            'grammar_exercises_system_prompt',
            [
                'rule_title' => $rule->title,
                'topic_name' => $rule->topicName,
                'language' => $rule->language,
                'count' => (string) $count,
                'instruction' => $instruction ?? '',
            ],
            fn () => ['system' => $this->buildSystemPrompt($rule, $count, $instruction), 'user' => '']
        );

        $result = $this->tracedCall->completeJson(
            $this->client,
            TraceContext::newTrace(),
            'grammar_exercises.completeJson',
            ['feature' => 'grammar_exercises', 'grammar_rule_id' => $rule->ruleId],
            $rendered->system,
            'Generate the exercises now.',
            $this->responseSchema(),
            $rendered->model,
        );

        $items = is_array($result['exercises'] ?? null) ? $result['exercises'] : [];

        $created = $this->drafts->createDrafts($ruleId, $items);

        if ($created === 0) {
            throw new AiClientException('AI did not return any usable exercises.');
        }

        return $created;
    }

    private function buildSystemPrompt(GrammarExerciseSource $rule, int $count, ?string $instruction): string
    {
        $language = $rule->language;
        $topicName = $rule->topicName;
        $examples = $rule->examples;

        $prompt = "You are a language-learning exercise writer. Write {$count} short practice exercises for the grammar point \"{$rule->title}\""
            .($topicName ? " (topic: {$topicName})" : '')
            ." in \"{$language}\", based on this explanation:\n\n{$rule->summary}\n\n{$rule->body}\n\n"
            .'Mix two exercise types: "cloze" (a natural sentence with the target grammar construction replaced by a blank shown as "_____", plus the exact correct answer for the blank) '
            .'and "multiple_choice" (a short question or sentence with a blank, 3-4 answer options where exactly one is correct, and the index of the correct option). '
            .'Each exercise needs a short one-sentence explanation of why the answer is correct. '
            .'Vary the sentences — do not just reuse the existing examples verbatim.';

        if ($examples !== '') {
            $prompt .= " Existing examples for this rule, for tone/style reference only: {$examples}";
        }

        if ($instruction !== null && trim($instruction) !== '') {
            $prompt .= " Additional instructions from the admin: {$instruction}";
        }

        return $prompt;
    }

    /**
     * @return array<string, mixed>
     */
    private function responseSchema(): array
    {
        return [
            'exercises' => [
                [
                    'type' => 'cloze|multiple_choice',
                    'prompt' => 'string, the sentence or question with the blank shown as _____',
                    'answer' => 'string, required for type=cloze, the exact text that fills the blank',
                    'options' => 'array of 3-4 strings, required for type=multiple_choice',
                    'answer_index' => 'integer, required for type=multiple_choice, 0-based index into options',
                    'explanation' => 'string, one sentence on why this is correct',
                ],
            ],
        ];
    }
}
