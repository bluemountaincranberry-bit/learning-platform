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
 * structured JSON, all five types of docs/product/grammar-exercises-block.md
 * (choose the form, build the sentence, fill the gap, transform, fix the
 * mistake) — no NLP/token-tagging of example sentences involved, the model
 * produces the finished exercise in one call, the same way
 * AiContentAnalysisService produces finished candidates.
 *
 * Exercises are persisted as status=draft. Admin-triggered ones
 * (origin=admin) wait for review in ExercisesRelationManager; learner-
 * triggered ones (origin=ai, VIK-31) are practiced right away, marked "AI"
 * and reportable. Prompts already in the pool go into the prompt as "do not
 * repeat", and duplicates are dropped on save.
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
    public function generate(int $ruleId, int $count = 5, ?string $instruction = null, string $origin = 'admin'): int
    {
        $rule = $this->drafts->source($ruleId);
        $existingPrompts = $this->drafts->existingPrompts($ruleId);
        $rendered = $this->promptRegistry->resolve(
            'grammar_exercises_system_prompt',
            [
                'rule_title' => $rule->title,
                'topic_name' => $rule->topicName,
                'language' => $rule->language,
                'count' => (string) $count,
                'instruction' => $instruction ?? '',
                'existing_prompts' => implode("\n", $existingPrompts),
            ],
            fn () => ['system' => $this->buildSystemPrompt($rule, $count, $instruction, $existingPrompts), 'user' => '']
        );

        $result = $this->tracedCall->completeJson(
            $this->client,
            TraceContext::newTrace(),
            'grammar_exercises.completeJson',
            ['feature' => 'grammar_exercises', 'grammar_rule_id' => $rule->ruleId, 'origin' => $origin],
            $rendered->system,
            'Generate the exercises now.',
            $this->responseSchema(),
            $rendered->model,
        );

        $items = is_array($result['exercises'] ?? null) ? $result['exercises'] : [];

        $created = $this->drafts->createDrafts($ruleId, $items, $origin);

        if ($created === 0) {
            throw new AiClientException('AI did not return any usable exercises.');
        }

        return $created;
    }

    /** @param  list<string>  $existingPrompts */
    private function buildSystemPrompt(GrammarExerciseSource $rule, int $count, ?string $instruction, array $existingPrompts = []): string
    {
        $language = $rule->language;
        $topicName = $rule->topicName;
        $examples = $rule->examples;
        $perType = max(1, intdiv($count, 5));

        $prompt = "You are a language-learning exercise writer. Write {$count} short practice exercises for the grammar point \"{$rule->title}\""
            .($topicName ? " (topic: {$topicName})" : '')
            ." in \"{$language}\", based on this explanation:\n\n{$rule->summary}\n\n{$rule->body}\n\n"
            ."Use all five exercise types, about {$perType} of each:\n"
            .'- "multiple_choice" (choose the form): a sentence with one blank shown as "_____", 3-4 options where exactly one is correct, and the 0-based answer_index.'."\n"
            .'- "build" (build the sentence): "prompt" = a short task or context (e.g. "Ask if they have ever been to Japan"), "answer" = the correct sentence, and "tiles" = that sentence split into 4-7 words or short chunks, in the correct order (the app shuffles them). Tiles must contain exactly the words of the answer, no extra or missing words.'."\n"
            .'- "cloze" (fill the gap): a sentence with one blank "_____", with the base verb in brackets after the blank when a verb form is asked, e.g. "She _____ (lose) her keys."; "answer" is the exact text for the blank.'."\n"
            .'- "transform": a correct sentence to rewrite; "instruction" says how in 2-4 words (e.g. "Make it a question", "Make it negative"); "answer" is the full rewritten sentence.'."\n"
            .'- "fix" (fix the mistake): a sentence with exactly one grammar mistake on this grammar point; "answer" is the full corrected sentence.'."\n"
            .'For typed types (cloze, transform, fix, build) add "accepted_answers": other fully correct variants (e.g. with a contraction or a different natural word order), or an empty list. '
            .'Every exercise needs a "hint" that nudges toward the rule without giving the answer (never include the answer or the correct option text in the hint), '
            .'and a one-sentence "explanation" of why the answer is correct. '
            .'Vary the sentences and contexts; keep them short and natural — do not just reuse the existing examples verbatim.';

        if ($examples !== '') {
            $prompt .= " Existing examples for this rule, for tone/style reference only: {$examples}";
        }

        if ($existingPrompts !== []) {
            $prompt .= "\n\nThese exercises already exist — do not repeat them or write near-copies:\n- ".implode("\n- ", $existingPrompts);
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
                    'type' => 'multiple_choice|build|cloze|transform|fix',
                    'instruction' => 'string, short task line, required for type=transform (e.g. "Make it a question"), optional otherwise',
                    'prompt' => 'string, the sentence (with the blank shown as _____ for cloze/multiple_choice)',
                    'answer' => 'string, required for build/cloze/transform/fix: the exact blank text (cloze) or the full correct sentence',
                    'accepted_answers' => 'array of strings, other correct variants for build/cloze/transform/fix, may be empty',
                    'options' => 'array of 3-4 strings, required for type=multiple_choice',
                    'answer_index' => 'integer, required for type=multiple_choice, 0-based index into options',
                    'tiles' => 'array of strings, required for type=build: the answer split into words/short chunks',
                    'hint' => 'string, a nudge toward the rule that never contains the answer',
                    'explanation' => 'string, one sentence on why this is correct',
                ],
            ],
        ];
    }
}
